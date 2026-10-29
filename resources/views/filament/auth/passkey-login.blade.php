@php
    // Send the user back to this panel after a passkey sign-in.
    session()->put('passkeys.redirect', filament()->getUrl());
@endphp
<div id="cbx-passkey" style="display:none;margin-top:1rem">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;color:var(--gray-400);font-size:.8rem">
        <span style="flex:1;height:1px;background:var(--gray-200)"></span>or<span style="flex:1;height:1px;background:var(--gray-200)"></span>
    </div>

    @if ($message = session()->pull('authenticatePasskey::message'))
        <p style="margin-bottom:.75rem;font-size:.85rem;color:var(--danger-600)">{{ $message }}</p>
    @endif

    <form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}">@csrf</form>

    <x-filament::button type="button" color="gray" outlined icon="heroicon-o-finger-print" class="w-full" style="width:100%" onclick="cbxPasskeyLogin(this)">
        Sign in with a passkey
    </x-filament::button>
</div>

<script src="{{ asset('js/simplewebauthn-browser.umd.min.js') }}"></script>
<script>
    (function () {
        if (window.SimpleWebAuthnBrowser?.browserSupportsWebAuthn()) {
            document.getElementById('cbx-passkey').style.display = 'block';
        }
    })();

    async function cbxPasskeyLogin(button) {
        button.disabled = true;
        try {
            const options = await (await fetch(@js(route('passkeys.authentication_options')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
            const response = await SimpleWebAuthnBrowser.startAuthentication({ optionsJSON: options });
            const form = document.getElementById('passkey-login-form');
            form.addEventListener('formdata', ({ formData }) => {
                formData.set('remember', 'false');
                formData.set('start_authentication_response', JSON.stringify(response));
            }, { once: true });
            form.requestSubmit ? form.requestSubmit() : form.submit();
        } catch (e) {
            button.disabled = false;
        }
    }
</script>
