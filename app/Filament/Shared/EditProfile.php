<?php

namespace App\Filament\Shared;

use App\Billing\StkTopUpService;
use App\Livewire\ManagePasskeys;
use App\Models\AuditLog;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Account settings: identity summary + security score on the left;
 * details, password, 2FA and passkeys as focused cards on the right.
 */
class EditProfile extends BaseEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return 'Account settings';
    }

    public function getSubheading(): ?string
    {
        return 'Manage your details and how you sign in.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal details')
                ->description('Used on invoices, receipts and support replies.')
                ->icon('heroicon-o-identification')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    $this->getNameFormComponent()->inlineLabel(false),
                    $this->getEmailFormComponent()->inlineLabel(false),
                    $this->getPhoneFormComponent(),
                ]),
            Section::make('Password')
                ->description('Leave blank to keep your current password.')
                ->icon('heroicon-o-key')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    $this->getPasswordFormComponent()->inlineLabel(false),
                    $this->getPasswordConfirmationFormComponent()->inlineLabel(false),
                    $this->getCurrentPasswordFormComponent()->inlineLabel(false)->columnSpanFull(),
                ]),
        ]);
    }

    protected function getPhoneFormComponent(): TextInput
    {
        $merchantUser = ! $this->getUser()->is_admin && $this->getUser()->merchant_id;

        return TextInput::make('phone')
            ->label($merchantUser ? 'Business phone' : 'Phone number')
            ->tel()
            ->placeholder('0712 345 678')
            ->required($merchantUser)
            ->maxLength(20)
            ->helperText($merchantUser ? 'Your registered M-Pesa number. Used for top-up prompts and account contact.' : 'For account contact.')
            ->rule(fn () => function (string $attr, $value, \Closure $fail) {
                if (filled($value) && ! StkTopUpService::normalizePhone($value)) {
                    $fail('Enter a valid Safaricom number, e.g. 0712 345 678.');
                }
            })
            ->dehydrateStateUsing(fn ($state) => filled($state) ? StkTopUpService::normalizePhone($state) : null)
            ->inlineLabel(false);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();
        $phone = (! $user->is_admin && $user->merchant) ? $user->merchant->phone : $user->phone;
        $normal = StkTopUpService::normalizePhone($phone);

        return [...$data, 'phone' => $normal ? '0'.substr($normal, -9) : $phone];
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $phone = $data['phone'] ?? null;
        unset($data['phone']);

        if (! $record->is_admin && $record->merchant) {
            if ($record->merchant->phone !== $phone) {
                $record->merchant->update(['phone' => $phone]);
                AuditLog::record('merchant.phone_changed', $record->merchant, [], $record->merchant_id);
            }
        } else {
            $record->forceFill(['phone' => $phone]);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    public function content(Schema $schema): Schema
    {
        $mfa = $this->getMultiFactorAuthenticationContentComponent();
        if ($mfa instanceof Section) {
            $mfa->label(null)
                ->heading('Two-factor authentication')
                ->description('Ask for a one-time code after your password.')
                ->icon('heroicon-o-shield-check')
                ->secondary(false)
                ->compact(false);
        }

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                View::make('filament.profile.summary')
                    ->viewData(fn () => $this->summaryData())
                    ->columnSpan(1),
                Group::make(array_values(array_filter([
                    $this->getFormContentComponent(),
                    $mfa,
                    Section::make('Passkeys')
                        ->description('Sign in with your fingerprint, face or device PIN. No password needed.')
                        ->icon('heroicon-o-finger-print')
                        ->schema([Livewire::make(ManagePasskeys::class)->key('passkeys')]),
                ])))->columnSpan(['default' => 1, 'lg' => 2]),
            ]),
        ]);
    }

    protected function summaryData(): array
    {
        $user = $this->getUser()->fresh();
        $hasApp = filled($user->getAppAuthenticationSecret());
        $hasEmail = $user->hasEmailAuthentication();
        $passkeys = $user->passkeys()->count();

        $checks = [
            ['Password set', true, 'Use a long, unique password.'],
            ['Two-factor authentication', $hasApp || $hasEmail, $hasApp ? 'Authenticator app' : ($hasEmail ? 'Email codes' : 'Turn on below')],
            ['Passkey added', $passkeys > 0, $passkeys ? $passkeys.' '.str('passkey')->plural($passkeys) : 'Fastest, phishing-proof sign-in'],
        ];
        $score = collect($checks)->where(1, true)->count();

        return [
            'user' => $user,
            'avatar' => filament()->getUserAvatarUrl($user),
            'roleLabel' => $user->is_admin ? 'Role' : 'Business',
            'role' => $user->is_admin ? 'Platform administrator' : ($user->merchant?->name ?? 'Merchant'),
            'merchantNo' => $user->merchant?->account_number,
            'phone' => ($p = StkTopUpService::normalizePhone(! $user->is_admin && $user->merchant ? $user->merchant->phone : $user->phone))
                ? '0'.substr($p, 1 + 2, 3).' '.substr($p, 6, 3).' '.substr($p, 9) : null,
            'checks' => $checks,
            'score' => $score,
            'level' => [1 => ['Basic', 'danger'], 2 => ['Good', 'warning'], 3 => ['Strong', 'success']][$score],
        ];
    }
}
