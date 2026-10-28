<?php

namespace App\Filament\Admin\Resources\PaymentProviders;

use App\Filament\Admin\Resources\PaymentProviders\Pages\ManagePaymentProviders;
use App\Models\PaymentProvider;
use App\Payments\Unintegrated\UnintegratedAdapter;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProvider::class;

    protected static ?string $modelLabel = 'provider';

    protected static ?string $navigationLabel = 'Providers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 7;

    public const KINDS = ['mobile_money' => 'Mobile money', 'bank' => 'Bank', 'card' => 'Card processor'];

    public static function normalizeCode(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')->replaceMatches('/^[0-9_]+/', '')->limit(40, '');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100)
                ->placeholder('e.g. Co-operative Bank of Kenya')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, string $operation) => $operation === 'create'
                    ? $set('code', self::normalizeCode((string) $state))
                    : null),
            TextInput::make('code')->required()->maxLength(40)
                // Clean pasted/typed values: "Coop Bank " -> "coop_bank".
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set) => $set('code', self::normalizeCode((string) $state)))
                ->dehydrateStateUsing(fn ($state) => self::normalizeCode((string) $state))
                ->regex('/^[a-z][a-z0-9_]*$/')
                ->validationMessages(['regex' => 'Use lowercase letters, numbers and underscores, starting with a letter (e.g. coop_bank).'])
                ->unique(PaymentProvider::class, 'code', ignoreRecord: true)
                ->helperText('Permanent identifier used in the API (e.g. "coop_bank"). Lowercase letters, numbers and _.')
                ->disabledOn('edit'),
            Select::make('kind')->options(self::KINDS)->required()->default('bank'),
            Select::make('adapter')->label('Integration')
                ->options(config('coinbox.adapters'))
                ->default(UnintegratedAdapter::class)
                ->required()
                ->helperText('Choose "Not integrated yet" until a developer has built the integration for this provider.'),
            Toggle::make('is_enabled')->label('Enabled')->default(false)
                ->helperText('Only enable once the integration is live.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->weight('bold')->searchable(),
            TextColumn::make('code')->badge(),
            TextColumn::make('kind')->formatStateUsing(fn ($state) => self::KINDS[$state] ?? $state),
            TextColumn::make('adapter')->label('Integration')
                ->formatStateUsing(fn ($state) => config('coinbox.adapters')[$state] ?? 'Not integrated yet'),
            TextColumn::make('capabilities')->state(fn (PaymentProvider $r) => $r->adapter()->capabilities() ?: ['none'])->badge(),
            TextColumn::make('payment_accounts_count')->counts('paymentAccounts')->label('Accounts'),
            IconColumn::make('is_enabled')->boolean()->label('Enabled'),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn (PaymentProvider $r) => $r->code !== PaymentProvider::MPESA && ! $r->paymentAccounts()->exists()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentProviders::route('/')];
    }
}
