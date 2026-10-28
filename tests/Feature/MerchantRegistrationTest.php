<?php

namespace Tests\Feature;

use App\Filament\Merchant\Pages\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MerchantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_sign_up(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('merchant');

        Livewire::test(Register::class)
            ->fillForm([
                'business_name' => 'Mama Mboga Ltd',
                'phone' => '254712345678',
                'name' => 'Jane Wanjiku',
                'email' => 'jane@example.com',
                'password' => 'Secret#12345',
                'passwordConfirmation' => 'Secret#12345',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jane@example.com')->sole();
        $this->assertSame('Mama Mboga Ltd', $user->merchant->name);
        $this->assertFalse($user->is_admin);
    }

    public function test_pages_render(): void
    {
        $this->get('/merchant/login')->assertOk();
        $this->get('/merchant/register')->assertOk();
        $this->get('/admin/login')->assertOk();
    }
}
