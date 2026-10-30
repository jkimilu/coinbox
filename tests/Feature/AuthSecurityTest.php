<?php

namespace Tests\Feature;

use App\Livewire\ManagePasskeys;
use App\Models\Merchant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screens_have_home_link_and_passkey_button(): void
    {
        foreach (['/admin/login', '/merchant/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Back to home')
                ->assertSee('Sign in with a passkey')
                ->assertSee('js/simplewebauthn-browser.umd.min.js', false);
        }
        $this->get('/merchant/register')->assertOk()->assertSee('Back to home');
    }

    public function test_passkey_authentication_options_endpoint(): void
    {
        $this->getJson('/passkeys/authentication-options')->assertOk()->assertJsonStructure(['challenge']);
    }

    public function test_profile_pages_show_mfa_and_passkeys(): void
    {
        $merchant = Merchant::create(['name' => 'M', 'email' => 'm@example.com', 'status' => 'active']);
        $user = User::factory()->create(['merchant_id' => $merchant->id, 'email_verified_at' => now()]);
        $this->actingAs($user)->get('/merchant/profile')->assertOk()
            ->assertSee('Passkeys')->assertSee('Authenticator app', false);

        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/admin/profile')->assertOk()->assertSee('Passkeys');
    }

    public function test_passkey_registration_starts_with_a_name(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        Livewire::test(ManagePasskeys::class)
            ->call('startRegistration')->assertHasErrors(['name'])
            ->set('name', 'Work laptop')->call('startRegistration')
            ->assertHasNoErrors()->assertDispatched('coinbox-passkey-options');

        $this->assertNotNull(session('passkey-registration-options'));
    }

    public function test_mfa_secrets_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['code-1', 'code-2']);

        $raw = \DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $raw->app_authentication_secret);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertSame(['code-1', 'code-2'], $user->fresh()->getAppAuthenticationRecoveryCodes());
    }

    public function test_profile_phone_edits_merchant_registered_phone(): void
    {
        $merchant = Merchant::create(['name' => 'M', 'email' => 'm@example.com', 'phone' => '254712345678', 'status' => 'active']);
        $user = User::factory()->create(['merchant_id' => $merchant->id, 'email_verified_at' => now()]);
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        $this->get('/merchant/profile')->assertOk()->assertSee('Business phone')->assertSee('0712 345 678');

        Livewire::test(\App\Filament\Shared\EditProfile::class)
            ->assertSet('data.phone', '0712345678')
            ->set('data.phone', '0812')->call('save')->assertHasErrors(['data.phone'])
            ->set('data.phone', '+254 722 000 111')->call('save')->assertHasNoErrors();

        $this->assertSame('254722000111', $merchant->fresh()->phone);
    }
}
