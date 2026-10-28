<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Short numeric merchant number used as the M-Pesa account reference (e.g. "234#INV001").
        DB::statement('CREATE SEQUENCE IF NOT EXISTS merchants_account_number_seq START 101');
        Schema::table('merchants', function (Blueprint $table) {
            $table->bigInteger('account_number')->nullable()->unique()->after('id');
        });
        DB::statement("UPDATE merchants SET account_number = nextval('merchants_account_number_seq') WHERE account_number IS NULL");
        DB::statement("ALTER TABLE merchants ALTER COLUMN account_number SET DEFAULT nextval('merchants_account_number_seq')");
        DB::statement('ALTER TABLE merchants ALTER COLUMN account_number SET NOT NULL');

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);                  // INV001, per merchant
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('processed_volume', 18, 2);
            $table->unsignedInteger('payment_count');
            $table->decimal('fee_percent', 6, 3);
            $table->unsignedBigInteger('free_threshold');
            $table->decimal('amount', 18, 2);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->string('status')->default('open');     // open, paid, void
            $table->timestamp('issued_at');
            $table->date('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'period_start']);
        });

        // Prepaid float: signed entries, balance = sum(amount).
        Schema::create('float_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('type');                        // topup, invoice_charge, adjustment
            $table->decimal('amount', 18, 2);              // + credit, - debit
            $table->foreignUlid('billing_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();       // M-Pesa receipt
            $table->string('description')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchant_id', 'created_at']);
            $table->unique(['type', 'reference']);         // one top-up per receipt
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->timestamp('low_float_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('float_entries');
        Schema::dropIfExists('billing_invoices');
        Schema::table('merchants', fn (Blueprint $t) => $t->dropColumn(['account_number', 'low_float_notified_at']));
        DB::statement('DROP SEQUENCE IF EXISTS merchants_account_number_seq');
    }
};
