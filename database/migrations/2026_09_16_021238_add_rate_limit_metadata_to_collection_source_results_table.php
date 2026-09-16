<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collection_source_results', function (Blueprint $table): void {
            $table->unsignedSmallInteger('http_status')->nullable()->after('error_message');
            $table->timestamp('retry_at')->nullable()->index()->after('http_status');
        });

        DB::table('collection_source_results')
            ->where('status', 'failed')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(error_message) LIKE ?', ['%status code 429%'])
                    ->orWhereRaw('LOWER(error_message) LIKE ?', ['%error code: 1015%']);
            })
            ->orderBy('id')
            ->each(function (object $result): void {
                DB::table('collection_source_results')
                    ->where('id', $result->id)
                    ->update([
                        'status' => 'rate_limited',
                        'http_status' => 429,
                        'retry_at' => Carbon::parse($result->updated_at)->addHour(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('collection_source_results')
            ->whereIn('status', ['rate_limited', 'deferred'])
            ->update(['status' => 'failed']);

        Schema::table('collection_source_results', function (Blueprint $table): void {
            $table->dropColumn(['http_status', 'retry_at']);
        });
    }
};
