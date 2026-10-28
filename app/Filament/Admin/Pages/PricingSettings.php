<?php

namespace App\Filament\Admin\Pages;

use App\Models\AuditLog;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Support\Billing;
use App\Support\Pricing;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PricingSettings extends Page
{
    protected string $view = 'filament.admin.pages.pricing-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Pricing';

    protected static ?string $title = 'Pricing';

    protected static ?string $slug = 'pricing';

    protected static ?int $navigationSort = 9;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fee_percent' => Pricing::feePercent(),
            'free_threshold' => Pricing::freeThreshold(),
            'paybill' => Billing::payBill(),
            'platform_account_id' => Billing::platformAccountId(),
            'due_days' => Billing::dueDays(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Processing fee')
                    ->description('Shown on the public website and used for merchant billing. Free while a merchant’s processed volume for the month is below the allowance; from it, the fee applies to the whole month’s processed volume. CoinBox never holds merchant funds, so fees are invoiced monthly.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fee_percent')->label('Fee')
                            ->numeric()->required()->minValue(0)->maxValue(100)->step(0.01)
                            ->suffix('%')
                            ->helperText('Percentage of monthly processed volume, e.g. 1 or 0.05.'),
                        TextInput::make('free_threshold')->label('Free until (per month)')
                            ->numeric()->required()->integer()->minValue(0)
                            ->prefix('KES')
                            ->helperText('Monthly volume below this is free.'),
                    ]),
                Section::make('How merchants pay CoinBox')
                    ->description('Merchants prepay a float or pay invoices via M-Pesa. Account number = merchant number (float top-up) or merchant number#invoice (e.g. 234#INV001).')
                    ->columns(3)
                    ->schema([
                        TextInput::make('paybill')->label('CoinBox PayBill')->required()->regex('/^\d{5,8}$/'),
                        Select::make('platform_account_id')->label('Connected account receiving these payments')
                            ->options(fn () => PaymentAccount::query()->with('merchant')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->external_account_reference} · {$a->display_name} ({$a->merchant?->name})"]))
                            ->placeholder('Not connected: record payments manually')
                            ->helperText('Connect the CoinBox PayBill as an account, then pick it so payments are matched automatically.'),
                        TextInput::make('due_days')->label('Invoice due after')->numeric()->integer()->minValue(0)->maxValue(90)->suffix('days')->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $before = ['fee_percent' => Pricing::feePercent(), 'free_threshold' => Pricing::freeThreshold()];

        Setting::put('pricing.fee_percent', (float) $data['fee_percent']);
        Setting::put('pricing.free_threshold', (int) $data['free_threshold']);
        Setting::put('billing.paybill', (string) $data['paybill']);
        Setting::put('billing.platform_account_id', $data['platform_account_id'] ?: null);
        Setting::put('billing.due_days', (int) $data['due_days']);

        AuditLog::record('settings.pricing_updated', null, ['before' => $before, 'after' => $data]);

        Notification::make()->success()->title('Pricing saved')
            ->body('Saved. The website now shows '.Pricing::feeLabel().' from '.Pricing::thresholdLabel().' a month.')->send();
    }
}
