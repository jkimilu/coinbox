<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One inbox for merchant support tickets and website contact-form messages.
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('number')->unique();           // human ref, e.g. #1042
            $table->string('source');                                 // merchant, contact_form
            $table->string('mailbox');                                // support, sales
            $table->foreignUlid('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('requester_phone')->nullable();
            $table->string('company')->nullable();
            $table->string('subject');
            $table->string('category')->nullable();                   // payments, integration, billing, account, sales, other
            $table->string('priority')->default('normal');            // low, normal, high, urgent
            $table->string('status')->default('open');                // open, awaiting_customer, resolved, closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_customer_reply_at')->nullable();
            $table->timestamp('last_staff_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
        DB::statement('CREATE SEQUENCE IF NOT EXISTS tickets_number_seq START 1001');
        DB::statement("ALTER TABLE tickets ALTER COLUMN number SET DEFAULT nextval('tickets_number_seq')");

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type');                            // customer, staff, note
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->string('sent_to')->nullable();
            $table->jsonb('cc')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        DB::statement('DROP SEQUENCE IF EXISTS tickets_number_seq');
    }
};
