<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account numbers within one PayBill/Till, e.g. PayBill 4072129 account "100" → its own webhook.
        Schema::create('account_routes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('payment_account_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);                 // what customers type before "#"
            $table->string('name');
            $table->foreignUlid('callback_endpoint_id')->nullable()->constrained('callback_endpoints')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payment_account_id', 'code']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUlid('account_route_id')->nullable()->after('payment_account_id')->constrained('account_routes')->nullOnDelete();
            $table->index(['merchant_id', 'account_route_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $t) => $t->dropConstrainedForeignId('account_route_id'));
        Schema::dropIfExists('account_routes');
    }
};
