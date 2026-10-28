<?php

namespace App\Filament\Merchant\Resources\PaymentAccounts;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\PaymentAccounts\Pages\ManagePaymentAccounts;
use App\Models\CallbackEndpoint;
use App\Models\PaymentAccount;
use App\Payments\CollectionSummary;
use App\Payments\Mpesa\DarajaClient;
use App\Payments\Mpesa\MpesaAccountConnector;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

class PaymentAccountResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = PaymentAccount::class;

    protected static ?string $modelLabel = 'M-Pesa account';

    protected static ?string $navigationLabel = 'M-Pesa accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?int $navigationSort = 1;

    /** Fields shared by the connect wizard and edit form. */
    public static function settingsFields(): array
    {
        return [
            Select::make('callback_endpoint_id')
                ->label('Send notifications to')
                ->helperText('Leave empty to notify all of your active webhook endpoints.')
                ->options(fn () => CallbackEndpoint::query()->where('merchant_id', auth()->user()->merchant_id)->pluck('url', 'id'))
                ->placeholder('All webhook endpoints'),
            Toggle::make('configuration.require_account_reference')
                ->label('Reject PayBill payments without an account number')
                ->helperText('Only applies if Safaricom has enabled validation on your shortcode.'),
            TextInput::make('configuration.min_amount')->label('Minimum amount (KES)')->numeric()->minValue(1)
                ->helperText('Only applies if validation is enabled.'),
            Radio::make('configuration.validation_fallback')
                ->label('If our validation URL is unreachable, Safaricom should')
                ->options(['Completed' => 'Complete the payment (recommended)', 'Cancelled' => 'Cancel the payment'])
                ->default('Completed'),
        ];
    }

    public static function credentialFields(bool $required = true): array
    {
        return [
            Radio::make('environment')
                ->options(['production' => 'Production (live payments)', 'sandbox' => 'Sandbox (testing)'])
                ->default('production')->required()->inline(),
            TextInput::make('consumer_key')->label('Consumer key')->required($required)->password()->revealable()
                ->helperText($required ? null : 'Leave blank to keep the current value.'),
            TextInput::make('consumer_secret')->label('Consumer secret')->required($required)->password()->revealable()
                ->helperText($required ? null : 'Leave blank to keep the current value.'),
            TextInput::make('passkey')->label('Lipa na M-Pesa Online passkey (optional)')->password()->revealable()
                ->helperText('Not needed for receiving C2B payments. Stored for future STK push support.'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('display_name')->required()->maxLength(100),
            TextInput::make('external_account_reference')
                ->label(fn ($record) => $record?->account_type === 'till' ? 'Store number (Head Office / HO number)' : 'PayBill number')
                ->helperText('Changing this clears the URL registration. Click "Register URLs with Safaricom" afterwards.')
                ->required()->regex('/^\d{5,8}$/')
                ->unique(PaymentAccount::class, 'external_account_reference', ignoreRecord: true)
                ->validationMessages(['unique' => 'This shortcode is already connected to CoinBox.']),
            TextInput::make('configuration.till_number')->label('Till number customers pay to')
                ->visible(fn ($record) => $record?->account_type === 'till')->regex('/^\d{5,8}$/'),
            ...self::settingsFields(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextEntry::make('display_name'),
                TextEntry::make('account_type')->formatStateUsing(fn ($state) => $state === 'till' ? 'Till (Buy Goods)' : 'PayBill')->badge(),
                TextEntry::make('external_account_reference')->label(fn ($record) => $record->account_type === 'till' ? 'Store (HO) number' : 'PayBill number'),
                TextEntry::make('configuration.till_number')->label('Till number')->visible(fn ($record) => $record->account_type === 'till'),
                TextEntry::make('credentials.environment')->label('Environment')->badge(),
                TextEntry::make('status')->badge()->color(fn ($state) => match ($state) { 'active' => 'success', 'pending' => 'warning', default => 'gray' }),
                TextEntry::make('urls_registered_at')->label('URLs registered')->dateTime()->placeholder('Not yet registered'),
            ]),
            Section::make('Callback URLs')
                ->description('These are registered with Safaricom for your shortcode. Keep them private: they contain a secret key.')
                ->schema([
                    TextEntry::make('confirmation_url')->label('Confirmation URL')->state(fn (PaymentAccount $r) => $r->confirmationUrl())->copyable()->fontFamily('mono'),
                    TextEntry::make('validation_url')->label('Validation URL')->state(fn (PaymentAccount $r) => $r->validationUrl())->copyable()->fontFamily('mono'),
                    TextEntry::make('registration_state')->label('Registration status')
                        ->state(fn (PaymentAccount $r) => match (true) {
                            $r->urls_registered_at === null => 'Not registered',
                            app(MpesaAccountConnector::class)->registeredUrlsAreCurrent($r) => 'Up to date',
                            default => 'URLs changed since registration: register again',
                        })
                        ->badge()->color(fn ($state) => $state === 'Up to date' ? 'success' : 'warning'),
                    TextEntry::make('configuration.registered.confirmation_url')->label('Confirmation URL registered with Safaricom')
                        ->placeholder('—')->fontFamily('mono')
                        ->visible(fn (PaymentAccount $r) => $r->urls_registered_at && ! app(MpesaAccountConnector::class)->registeredUrlsAreCurrent($r)),
                    TextEntry::make('last_registration_response')->label('Last Safaricom response')->placeholder('—')->fontFamily('mono'),
                ]),
            Section::make('Recorded collections')
                ->description('Totals of payments recorded by CoinBox. This is not your M-Pesa balance.')
                ->columns(3)
                ->schema([
                    TextEntry::make('s_received')->label('Successful payments')->state(fn ($record) => self::summary($record)['all_time']['successful_payments']['amount'])->money('KES'),
                    TextEntry::make('s_reversed')->label('Reversals')->state(fn ($record) => self::summary($record)['all_time']['reversals']['amount'])->money('KES'),
                    TextEntry::make('s_net')->label('Net recorded')->state(fn ($record) => self::summary($record)['all_time']['net_recorded_collections'])->money('KES'),
                ]),
        ]);
    }

    protected static function summary(PaymentAccount $account): array
    {
        static $cache = [];

        return $cache[$account->id] ??= app(CollectionSummary::class)->forAccount($account);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->searchable()->weight('bold'),
                TextColumn::make('account_type')->label('Type')->formatStateUsing(fn ($state) => $state === 'till' ? 'Till' : 'PayBill')->badge(),
                TextColumn::make('external_account_reference')->label('Shortcode')->searchable(),
                TextColumn::make('credentials.environment')->label('Env')->badge()->color(fn ($state) => $state === 'sandbox' ? 'warning' : 'success'),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'active' => 'success', 'pending' => 'warning', default => 'gray' }),
                TextColumn::make('urls_registered_at')->label('URLs registered')->since()->placeholder('Not registered'),
                TextColumn::make('transactions_count')->counts('transactions')->label('Payments'),
            ])
            ->emptyStateHeading('No M-Pesa accounts connected')
            ->emptyStateDescription('Connect your PayBill or Till to start receiving payment notifications.')
            ->recordActions([
                ViewAction::make()->label('Setup details'),
                ActionGroup::make([
                    self::registerUrlsAction(),
                    EditAction::make(),
                    self::credentialsAction(),
                    self::simulateAction(),
                    Action::make('toggle')
                        ->label(fn ($record) => $record->status === 'disabled' ? 'Enable' : 'Disable')
                        ->icon(Heroicon::OutlinedPower)
                        ->requiresConfirmation()
                        ->action(fn (PaymentAccount $record) => $record->update(['status' => $record->status === 'disabled' ? 'active' : 'disabled'])),
                ]),
            ]);
    }

    public static function registerUrlsAction(): Action
    {
        return Action::make('registerUrls')
            ->label('Register URLs with Safaricom')
            ->icon(Heroicon::OutlinedLink)
            ->requiresConfirmation()
            ->modalDescription('CoinBox will use your Daraja app credentials to register its confirmation and validation URLs for this shortcode. Any URLs previously registered for this shortcode by another system will be replaced.')
            ->action(function (PaymentAccount $record) {
                try {
                    $result = app(MpesaAccountConnector::class)->registerUrls($record);
                    $result['status'] === 'already_current'
                        ? Notification::make()->success()->title('Already registered')->body('Safaricom is already sending this shortcode\'s payments to CoinBox\'s current URLs. Nothing to update.')->send()
                        : Notification::make()->success()->title('URLs registered with Safaricom')->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Registration failed')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function credentialsAction(): Action
    {
        return Action::make('credentials')
            ->label('Update Daraja credentials')
            ->icon(Heroicon::OutlinedKey)
            ->fillForm(fn (PaymentAccount $record) => ['environment' => $record->credentials?->environment])
            ->schema(self::credentialFields(required: false))
            ->action(function (PaymentAccount $record, array $data) {
                app(MpesaAccountConnector::class)->updateCredentials($record, $data);
                Notification::make()->success()->title('Credentials updated')->send();
            });
    }

    public static function simulateAction(): Action
    {
        return Action::make('simulate')
            ->label('Send test payment (sandbox)')
            ->icon(Heroicon::OutlinedBeaker)
            ->visible(fn (PaymentAccount $record) => $record->credentials?->environment === 'sandbox')
            ->schema([
                Text::make('Sandbox test shortcodes are shared, so CoinBox re-registers its URLs first. Real phones cannot pay sandbox shortcodes.'),
                TextInput::make('amount')->numeric()->required()->default(10),
                TextInput::make('reference')->label('Account number')->default('TEST-001'),
                TextInput::make('msisdn')->label('Test phone')->default('254708374149'),
            ])
            ->action(function (PaymentAccount $record, array $data) {
                // Sandbox shortcodes are shared, so another developer may have taken over the callback URLs.
                // Re-claim them right before simulating (best effort; the sandbox is often flaky).
                for ($i = 0; $i < 3; $i++) {
                    try {
                        app(DarajaClient::class)->registerUrls($record);
                        break;
                    } catch (Throwable) {
                        usleep(1_500_000);
                    }
                }

                try {
                    $res = app(DarajaClient::class)->simulate($record, (string) $data['amount'], (string) $data['reference'], (string) $data['msisdn']);
                    Notification::make()->info()->title('Safaricom response')->body(json_encode($res))->persistent()->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Simulation failed')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentAccounts::route('/'),
        ];
    }
}
