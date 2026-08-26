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
        Schema::create('odds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_run_id')->constrained()->cascadeOnDelete();
            $table->decimal('home_odd', 8, 3);
            $table->decimal('draw_odd', 8, 3);
            $table->decimal('away_odd', 8, 3);
            $table->timestamp('collected_at')->index();
            $table->timestamps();

            $table->unique(['source_event_id', 'collection_run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds');
    }
};
