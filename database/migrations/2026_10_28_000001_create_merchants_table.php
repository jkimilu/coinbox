<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('status')->default('active'); // active, suspended
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('merchant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn('is_admin');
        });
        Schema::dropIfExists('merchants');
    }
};
