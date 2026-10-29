<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Filament\Notifications\Notification;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

/** Profile-page section to add and remove passkeys (styled for Filament). */
class ManagePasskeys extends Component
{
    public string $name = '';

    public function render(): View
    {
        return view('livewire.manage-passkeys', [
            'passkeys' => auth()->user()->passkeys()->latest()->get(),
        ]);
    }

    public function startRegistration(): void
    {
        $this->validate(['name' => 'required|string|max:100']);

        $options = Config::getAction('generate_passkey_register_options', GeneratePasskeyRegisterOptionsAction::class)
            ->execute(auth()->user());

        session()->put('passkey-registration-options', $options);

        $this->dispatch('coinbox-passkey-options', options: json_decode($options));
    }

    public function storePasskey(string $passkey): void
    {
        try {
            Config::getAction('store_passkey', StorePasskeyAction::class)->execute(
                auth()->user(),
                $passkey,
                session()->pull('passkey-registration-options'),
                request()->getHost(),
                ['name' => $this->name],
            );
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Could not save the passkey')->body('Please try again.')->send();

            return;
        }

        AuditLog::record('auth.passkey_added', auth()->user(), ['name' => $this->name]);
        Notification::make()->success()->title('Passkey added')->body('You can now sign in with it from the login page.')->send();
        $this->name = '';
    }

    public function registrationFailed(string $message = ''): void
    {
        session()->forget('passkey-registration-options');
        Notification::make()->warning()->title('Passkey not added')->body($message ?: 'The request was cancelled or timed out.')->send();
    }

    public function deletePasskey(int $id): void
    {
        $passkey = auth()->user()->passkeys()->whereKey($id)->first();
        if ($passkey) {
            $passkey->delete();
            AuditLog::record('auth.passkey_removed', auth()->user(), ['name' => $passkey->name]);
            Notification::make()->success()->title('Passkey removed')->send();
        }
    }
}
