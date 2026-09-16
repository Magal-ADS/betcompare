<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->index();
            $table->string('region', 32)->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->string('country_code', 3)->nullable()->index();
            $table->string('competition')->nullable()->index();
        });

        Schema::table('source_events', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->index();
            $table->string('region', 32)->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('competition')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('source_events', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'region', 'country', 'country_code', 'competition']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'region', 'country', 'country_code', 'competition']);
        });
    }
};
