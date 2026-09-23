<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['events', 'source_events'] as $table) {
            DB::table($table)
                ->whereNotNull('starts_at')
                ->orderBy('id')
                ->chunkById(500, function (Collection $events) use ($table): void {
                    foreach ($events as $event) {
                        DB::table($table)->where('id', $event->id)->update([
                            'starts_at' => CarbonImmutable::parse(
                                $event->starts_at,
                                config('app.display_timezone'),
                            )->utc()->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Historical timestamps must not be shifted again after new collections.
    }
};
