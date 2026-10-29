<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Admin\Widgets\AttentionNeeded;
use App\Filament\Admin\Widgets\PlatformStats;
use App\Filament\Admin\Widgets\PlatformVolumeChart;
use App\Filament\Admin\Widgets\RecentActivity;
use App\Filament\Admin\Widgets\SupportQueue;
use App\Filament\Admin\Widgets\TopMerchants;
use App\Filament\Merchant\Resources\Support\Pages\ListTickets as MerchantTickets;
use App\Mail\TicketReceived;
use App\Mail\TicketReply;
use App\Mail\TicketTeamAlert;
use App\Models\Merchant;
use App\Models\ProviderEvent;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SupportAndAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    public function test_contact_form_creates_ticket_and_emails_sales(): void
    {
        $this->get('/')->assertOk()->assertSee('Talk to a person')->assertSee('sales@coinbox.co.ke')->assertSee('support@coinbox.co.ke');

        $this->post('/contact', [
            'name' => 'Amina Otieno', 'email' => 'Amina@Example.com', 'company' => 'Soko Fiti', 'topic' => 'sales',
            'message' => 'We run 4 PayBills and want to integrate all of them.',
        ])->assertRedirect(url('/').'#contact')->assertSessionHas('contact_sent');

        $t = Ticket::sole();
        $this->assertSame(['contact_form', 'sales', 'amina@example.com'], [$t->source, $t->mailbox, $t->requester_email]);
        Mail::assertQueued(TicketTeamAlert::class, fn ($m) => $m->hasTo('sales@coinbox.co.ke'));
        Mail::assertQueued(TicketReceived::class, fn ($m) => $m->hasTo('amina@example.com'));
    }

    public function test_contact_form_validation_and_honeypot(): void
    {
        $this->post('/contact', ['name' => '', 'email' => 'bad', 'topic' => 'sales', 'message' => 'hi'])
            ->assertSessionHasErrorsIn('contact', ['name', 'email', 'message']);

        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@x.com', 'topic' => 'sales', 'message' => 'spam spam spam', 'website' => 'http://spam'])
            ->assertRedirect();
        $this->assertSame(0, Ticket::count());
    }

    public function test_merchant_ticket_and_admin_reply_with_cc(): void
    {
        $merchant = Merchant::create(['name' => 'Duka', 'email' => 'd@example.com', 'status' => 'active']);
        $user = User::factory()->create(['merchant_id' => $merchant->id, 'email' => 'owner@duka.co.ke', 'email_verified_at' => now()]);
        $this->actingAs($user);
        Filament::setCurrentPanel('merchant');

        Livewire::test(MerchantTickets::class)->callAction('new', data: [
            'subject' => 'Webhook not firing', 'category' => 'integration', 'priority' => 'high', 'message' => 'Payments arrive but our ERP gets nothing.',
        ])->assertHasNoActionErrors();

        $ticket = Ticket::sole();
        $this->assertSame(['merchant', 'support', $merchant->id], [$ticket->source, $ticket->mailbox, $ticket->merchant_id]);
        Mail::assertQueued(TicketTeamAlert::class, fn ($m) => $m->hasTo('support@coinbox.co.ke'));
        $this->get('/merchant/support')->assertOk()->assertSee('Webhook not firing');
        $this->get('/merchant/support/'.$ticket->id)->assertOk()->assertSee('our ERP gets nothing');

        // Admin replies inline with CC
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Grace', 'email_verified_at' => now()]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $this->get('/admin/tickets')->assertOk();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->fillForm(['body' => 'Your endpoint returns 500. Please check your logs.', 'cc' => ['dev@duka.co.ke', 'OWNER@duka.co.ke'], 'status_after' => 'awaiting_customer'])
            ->call('sendReply')->assertHasNoFormErrors();

        Mail::assertQueued(TicketReply::class, fn ($m) => $m->hasTo('owner@duka.co.ke') && $m->hasCc('dev@duka.co.ke') && ! $m->hasCc('owner@duka.co.ke'));
        $ticket->refresh();
        $this->assertSame('awaiting_customer', $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_to);

        // Internal note: saved, not emailed
        Mail::fake();
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->set('data.body', 'Customer uses Odoo.')->call('addNote');
        $this->assertSame('note', $ticket->messages()->reorder()->latest('id')->first()->author_type);
        Mail::assertNothingQueued();

        // Merchant never sees the note
        $this->actingAs($user)->get('/merchant/support/'.$ticket->id)->assertDontSee('Customer uses Odoo.')->assertSee('Your endpoint returns 500');
    }

    public function test_admin_dashboard_widgets_and_attention_items(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));
        Filament::setCurrentPanel('admin');

        $this->assertSame([], AttentionNeeded::items());

        ProviderEvent::create(['provider_id' => \App\Models\PaymentProvider::first()->id, 'event_type' => 'c2b.confirmation', 'payload' => [], 'processing_status' => 'failed']);
        Ticket::create(['source' => 'contact_form', 'mailbox' => 'sales', 'requester_name' => 'A', 'requester_email' => 'a@x.com', 'subject' => 'Hi', 'priority' => 'urgent', 'last_customer_reply_at' => now()]);

        $titles = array_column(AttentionNeeded::items(), 2);
        $this->assertContains('Failed provider events (7d)', $titles);
        $this->assertContains('Tickets waiting for a reply', $titles);
        $this->assertSame('critical', AttentionNeeded::items()[0][0]);

        $this->get('/admin')->assertOk()->assertSee('Needs attention')->assertSee('Operations');
        foreach ([AttentionNeeded::class, PlatformStats::class, PlatformVolumeChart::class, SupportQueue::class, TopMerchants::class, RecentActivity::class] as $w) {
            Livewire::test($w)->assertOk();
        }
    }
}
