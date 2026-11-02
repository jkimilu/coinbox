<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Merchants can now send prompts from their own shortcodes through the API (purpose "collection").
        Schema::table('stk_requests', function (Blueprint $table) {
            $table->foreignUlid('api_key_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('description', 13)->nullable()->after('account_reference');
            $table->foreignUlid('transaction_id')->nullable()->after('mpesa_receipt')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stk_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_id');
            $table->dropConstrainedForeignId('transaction_id');
            $table->dropColumn('description');
        });
    }
};
