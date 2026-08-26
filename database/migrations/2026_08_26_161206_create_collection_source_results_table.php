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
        Schema::create('collection_source_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bookmaker_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->unsignedInteger('events_count')->default(0);
            $table->timestamp('collected_at')->nullable()->index();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->unique(['collection_run_id', 'bookmaker_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_source_results');
    }
};
