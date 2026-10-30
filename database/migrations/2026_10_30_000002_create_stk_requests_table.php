<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // M-Pesa STK push ("Lipa na M-Pesa Online") prompts, e.g. a merchant topping up their CoinBox float.
        Schema::create('stk_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('payment_account_id')->constrained();      // account the prompt is sent through
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose')->default('float_topup');
            $table->string('invoice_number')->nullable();
            $table->string('phone', 15);
            $table->unsignedInteger('amount');                              // whole KES (STK requires integers)
            $table->string('account_reference', 12);
            $table->string('callback_token', 48)->unique();
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('status')->default('pending');                  // pending, succeeded, failed, cancelled, timeout
            $table->string('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->string('mpesa_receipt')->nullable();
            $table->jsonb('callback_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stk_requests');
    }
};
