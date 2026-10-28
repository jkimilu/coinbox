<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use App\Payments\Mpesa\MpesaAdapter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProvider::query()->updateOrCreate(
            ['code' => PaymentProvider::MPESA],
            ['name' => 'M-Pesa (Safaricom Daraja)', 'kind' => 'mobile_money', 'adapter' => MpesaAdapter::class, 'is_enabled' => true],
        );
    }
}
