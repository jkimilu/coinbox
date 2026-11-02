<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Connecting an M-Pesa account needs this row. It used to come only from DatabaseSeeder,
 * which deploys never run, so production could be missing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_providers')->where('code', 'mpesa')->exists();

        if (! $exists) {
            DB::table('payment_providers')->insert([
                'code' => 'mpesa',
                'name' => 'M-Pesa (Safaricom Daraja)',
                'kind' => 'mobile_money',
                'adapter' => 'App\\Payments\\Mpesa\\MpesaAdapter',
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Left in place: payment accounts reference it.
    }
};
