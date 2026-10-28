<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PricingSettings;
use App\Models\User;
use App\Support\Pricing;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingAndPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_and_seo_files(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Every M-Pesa payment', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('Then 1% of what you process.', false)
            ->assertSee('name="keywords"', false);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
    }

    public function test_fee_rules(): void
    {
        $this->assertSame(0.0, Pricing::feeFor(9999));
        $this->assertSame(100.0, Pricing::feeFor(10000));
        $this->assertSame(2500.0, Pricing::feeFor(250000));
    }

    public function test_admin_can_change_pricing_and_website_updates(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('admin');

        $this->get('/admin/pricing')->assertOk();

        Livewire::test(PricingSettings::class)
            ->fillForm(['fee_percent' => 0.75, 'free_threshold' => 20000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0.75%', Pricing::feeLabel());
        $this->assertSame(0.0, Pricing::feeFor(15000));
        $this->assertSame(150.0, Pricing::feeFor(20000));

        $this->get('/')->assertSee('Then 0.75% of what you process.', false)->assertSee('KES 20,000', false);
    }
}
