<?php

namespace App\Filament\Merchant\Resources\PaymentAccounts\Pages;

use App\Filament\Merchant\Pages\SetupGuide;
use App\Filament\Merchant\Resources\PaymentAccounts\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Payments\Mpesa\MpesaAccountConnector;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ManagePaymentAccounts extends ManageRecords
{
    protected static string $resource = PaymentAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guide')->label('Setup guide')->icon(Heroicon::OutlinedBookOpen)->color('gray')
                ->url(SetupGuide::getUrl()),
            CreateAction::make()
                ->label('Connect M-Pesa account')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth('2xl')
                ->steps([
                    Step::make('Account')
                        ->description('Your PayBill or Till')
                        ->schema([
                            Radio::make('account_type')
                                ->options(['paybill' => 'PayBill', 'till' => 'Till number (Buy Goods)'])
                                ->default('paybill')->required()->inline()->live(),
                            TextInput::make('display_name')->label('Name in CoinBox')->required()->maxLength(100)
                                ->placeholder('e.g. Main shop collections'),
                            TextInput::make('external_account_reference')
                                ->label(fn (Get $get) => $get('account_type') === 'till' ? 'Store number (Head Office / HO number)' : 'PayBill number')
                                ->helperText(fn (Get $get) => $get('account_type') === 'till'
                                    ? 'Safaricom registers callback URLs against the store (HO) number, not the till number customers pay to. Find it on the M-Pesa Business portal under your organisation details.'
                                    : 'The business number customers enter when paying.')
                                ->required()->regex('/^\d{5,8}$/')
                                ->unique(PaymentAccount::class, 'external_account_reference')
                                ->validationMessages(['unique' => 'This shortcode is already connected to CoinBox.']),
                            TextInput::make('till_number')->label('Till number customers pay to')
                                ->visible(fn (Get $get) => $get('account_type') === 'till')->regex('/^\d{5,8}$/'),
                        ]),
                    Step::make('Daraja credentials')
                        ->description('From your Daraja app')
                        ->schema([
                            Text::make('Create an app on developer.safaricom.co.ke and take it live for this shortcode, then paste its Consumer Key and Consumer Secret here. They are encrypted and never shown again or exposed through the API.'),
                            ...PaymentAccountResource::credentialFields(),
                        ]),
                    Step::make('Notifications')
                        ->schema([
                            ...PaymentAccountResource::settingsFields(),
                            Toggle::make('register_now')->label('Register callback URLs with Safaricom now')->default(true),
                        ]),
                ])
                ->using(function (array $data) {
                    $account = app(MpesaAccountConnector::class)->connect(auth()->user()->merchant, [
                        ...$data,
                        ...($data['configuration'] ?? []),
                    ]);

                    if ($data['register_now'] ?? false) {
                        try {
                            $result = app(MpesaAccountConnector::class)->registerUrls($account);
                            Notification::make()->success()->title($result['status'] === 'already_current' ? 'Connected (URLs already registered)' : 'Connected and registered with Safaricom')->send();
                        } catch (Throwable $e) {
                            Notification::make()->warning()->title('Account saved, but URL registration failed')
                                ->body($e->getMessage().' — fix the credentials and use "Register URLs with Safaricom".')
                                ->persistent()->send();
                        }
                    }

                    return $account;
                })
                ->successNotification(null),
        ];
    }
}
