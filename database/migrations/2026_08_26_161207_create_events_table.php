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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('sport', 32);
            $table->string('market', 16);
            $table->string('home_team');
            $table->string('away_team');
            $table->string('normalized_home_team', 191);
            $table->string('normalized_away_team', 191);
            $table->string('event_date')->nullable();
            $table->string('event_time')->nullable();
            $table->timestamps();

            $table->index(['sport', 'market', 'normalized_home_team', 'normalized_away_team'], 'events_matching_index');
            $table->index(['event_date', 'event_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
