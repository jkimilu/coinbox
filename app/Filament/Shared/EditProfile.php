<?php

namespace App\Filament\Shared;

use App\Livewire\ManagePasskeys;
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
            'checks' => $checks,
            'score' => $score,
            'level' => [1 => ['Basic', 'danger'], 2 => ['Good', 'warning'], 3 => ['Strong', 'success']][$score],
        ];
    }
}
