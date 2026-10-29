<div>
    <form wire:submit="startRegistration" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-start">
        <div style="flex:1 1 16rem">
            <x-filament::input.wrapper :valid="! $errors->has('name')">
                <x-filament::input type="text" wire:model="name" placeholder="Name this passkey, e.g. Work laptop" autocomplete="off" />
            </x-filament::input.wrapper>
            @error('name')<p style="margin-top:.35rem;font-size:.8rem;color:var(--danger-600)">{{ $message }}</p>@enderror
        </div>
        <x-filament::button type="submit" icon="heroicon-o-finger-print" wire:loading.attr="disabled">Add passkey</x-filament::button>
    </form>

    <div style="margin-top:1.25rem;display:grid;gap:.5rem">
        @forelse ($passkeys as $passkey)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid var(--gray-200);border-radius:.6rem;padding:.65rem .9rem" class="dark:border-white/10">
                <div>
                    <p style="font-weight:600;font-size:.9rem">{{ $passkey->name }}</p>
                    <p style="font-size:.8rem;color:var(--gray-500)">Added {{ $passkey->created_at->diffForHumans() }} · {{ $passkey->last_used_at ? 'last used '.$passkey->last_used_at->diffForHumans() : 'not used yet' }}</p>
                </div>
                <x-filament::button color="danger" outlined size="sm" wire:click="deletePasskey({{ $passkey->id }})" wire:confirm="Remove this passkey?">Remove</x-filament::button>
            </div>
        @empty
            <p style="font-size:.85rem;color:var(--gray-500)">No passkeys yet.</p>
        @endforelse
    </div>
</div>

@assets
<script src="{{ asset('js/simplewebauthn-browser.umd.min.js') }}"></script>
@endassets

@script
<script>
    $wire.on('coinbox-passkey-options', async ({ options }) => {
        try {
            const passkey = await SimpleWebAuthnBrowser.startRegistration({ optionsJSON: options });
            $wire.storePasskey(JSON.stringify(passkey));
        } catch (e) {
            $wire.registrationFailed(e?.name === 'InvalidStateError' ? 'This device already has a passkey for your account.' : '');
        }
    });
</script>
@endscript
