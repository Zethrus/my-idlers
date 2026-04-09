<?php

namespace App\Console\Commands;

use App\Models\Locations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BackfillLocationCoordinates extends Command
{
    protected $signature = 'locations:backfill-coordinates {--force : Re-geocode all locations, including ones that already have coordinates}';

    protected $description = 'Resolve map coordinates from saved location names';

    public function handle(): int
    {
        Cache::forget('map_data');

        $query = Locations::query()->orderBy('name');

        if (!$this->option('force')) {
            $query->where(function ($innerQuery) {
                $innerQuery->whereNull('latitude')
                    ->orWhereNull('longitude');
            });
        }

        $locations = $query->get();

        if ($locations->isEmpty()) {
            $this->info($this->option('force')
                ? 'No locations found to re-geocode.'
                : 'No locations need coordinate backfilling.');
            return Command::SUCCESS;
        }

        $this->info($this->option('force')
            ? "Found {$locations->count()} locations to re-geocode."
            : "Found {$locations->count()} locations to geocode.");

        $bar = $this->output->createProgressBar($locations->count());
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($locations as $location) {
            if ($location->syncCoordinates()) {
                $updated++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("Failed to geocode {$location->name}");
            }

            $bar->advance();

            if (!$locations->last()->is($location)) {
                sleep(1);
            }
        }

        $bar->finish();
        $this->newLine(2);
        Cache::forget('map_data');
        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}