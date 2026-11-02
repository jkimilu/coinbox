<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('daily_report')->default(true); // receive the 3am collection report
        });
        Schema::table('merchants', function (Blueprint $table) {
            $table->date('daily_report_sent_for')->nullable(); // last report date sent, so reruns don't send twice
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('daily_report'));
        Schema::table('merchants', fn (Blueprint $t) => $t->dropColumn('daily_report_sent_for'));
    }
};
