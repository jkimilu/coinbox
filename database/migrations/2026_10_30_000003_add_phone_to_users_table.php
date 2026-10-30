<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admins' own phone. Merchant users edit their business phone (merchants.phone) instead.
        Schema::table('users', fn (Blueprint $t) => $t->string('phone', 20)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
    }
};
