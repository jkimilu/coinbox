<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // mpesa, bank_x, card_processor_x
            $table->string('name');
            $table->string('kind');                // mobile_money, bank, card
            $table->string('adapter');             // FQCN implementing PaymentProviderAdapter
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('payment_providers');
            $table->string('environment')->default('production'); // sandbox, production
            $table->text('secrets');               // encrypted JSON, never exposed via API
            $table->timestamps();
        });

        Schema::create('callback_endpoints', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->text('secret');                // encrypted signing secret
            $table->jsonb('events')->nullable();   // null = all events
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('payment_providers');
            $table->string('account_type');        // paybill, till
            $table->string('display_name');
            $table->string('external_account_reference'); // shortcode / store number
            $table->string('currency', 3)->default('KES');
            $table->foreignUlid('credentials_id')->nullable()->constrained('provider_credentials')->nullOnDelete();
            $table->foreignUlid('callback_endpoint_id')->nullable()->constrained('callback_endpoints')->nullOnDelete();
            $table->string('route_key', 64)->unique(); // secret path segment for provider callbacks
            $table->jsonb('configuration')->nullable();
            $table->string('status')->default('pending'); // pending, active, disabled
            $table->timestamp('urls_registered_at')->nullable();
            $table->text('last_registration_response')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'external_account_reference']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('provider_id')->constrained('payment_providers');
            $table->foreignUlid('payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');          // c2b.validation, c2b.confirmation
            $table->string('idempotency_key')->nullable();
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->string('processing_status')->default('received'); // received, processed, duplicate, failed, rejected
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider_id', 'event_type', 'idempotency_key']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained();
            $table->foreignUlid('payment_account_id')->constrained();
            $table->foreignId('provider_id')->constrained('payment_providers');
            $table->foreignUlid('provider_event_id')->nullable()->constrained('provider_events')->nullOnDelete();
            $table->string('type')->default('payment'); // payment, reversal
            $table->ulid('reverses_transaction_id')->nullable();
            $table->string('provider_code');       // denormalised: mpesa
            $table->string('payment_method');      // paybill, till
            $table->string('provider_transaction_id');
            $table->string('merchant_reference')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->string('status');              // pending, succeeded, failed, reversed
            $table->string('payer_identifier')->nullable(); // masked/hashed MSISDN as sent by provider
            $table->string('payer_name')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('paid_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'provider_transaction_id', 'type']);
            $table->index(['merchant_id', 'paid_at']);
            $table->index(['payment_account_id', 'paid_at']);
            $table->index(['merchant_id', 'merchant_reference']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('reverses_transaction_id')->references('id')->on('transactions');
        });

        Schema::create('callback_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('callback_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');               // transaction.succeeded, transaction.reversed
            $table->jsonb('payload');
            $table->string('status')->default('pending'); // pending, delivered, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_response_code')->nullable();
            $table->text('last_response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_account_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('source');              // provider_api, statement_import
            $table->string('status')->default('running'); // running, completed, failed
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('missing_in_gateway_count')->default(0);
            $table->unsignedInteger('missing_at_provider_count')->default(0);
            $table->unsignedInteger('amount_mismatch_count')->default(0);
            $table->jsonb('discrepancies')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 16)->unique();
            $table->string('token_hash', 64);
            $table->jsonb('abilities')->nullable(); // null = all read abilities
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->jsonb('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'api_keys', 'reconciliation_runs', 'callback_deliveries', 'transactions',
            'provider_events', 'payment_accounts', 'callback_endpoints', 'provider_credentials', 'payment_providers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
