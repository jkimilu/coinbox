<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reversals CoinBox asked Safaricom to perform (Daraja Reversal API). Safaricom answers later on a callback.
        Schema::create('reversal_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->constrained();             // the payment being reversed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->string('callback_token', 48)->unique();
            $table->string('conversation_id')->nullable();
            $table->string('originator_conversation_id')->nullable();
            $table->string('status')->default('pending');                     // pending, succeeded, failed, timeout
            $table->string('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->string('reversal_receipt')->nullable();
            $table->jsonb('result_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_requests');
    }
};
