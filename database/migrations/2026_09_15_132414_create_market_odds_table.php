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
        Schema::create('market_odds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_run_id')->constrained()->cascadeOnDelete();
            $table->string('market_key', 64)->index();
            $table->string('market_name');
            $table->string('selection_key', 191);
            $table->string('selection_name');
            $table->decimal('odd', 8, 3);
            $table->timestamp('collected_at')->index();
            $table->timestamps();

            $table->unique(
                ['source_event_id', 'collection_run_id', 'market_key', 'selection_key'],
                'market_odds_snapshot_unique',
            );
            $table->index(['collection_run_id', 'market_key'], 'market_odds_run_market_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_odds');
    }
};
