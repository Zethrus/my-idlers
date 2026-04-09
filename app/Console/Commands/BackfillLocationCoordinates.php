<?php

namespace App\Console\Commands;

use App\Models\Locations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BackfillLocationCoordinates extends Command
{
    protected $signature = 'locations:backfill-coordinates';

    protected $description = 'Resolve map coordinates from saved location names';

    public function handle(): int
    {
        Cache::forget('map_data');

        $locations = Locations::where(function ($query) {
            $query->whereNull('latitude')
                ->orWhereNull('longitude');
        })->orderBy('name')->get();

        if ($locations->isEmpty()) {
            $this->info('No locations need coordinate backfilling.');
            return Command::SUCCESS;
        }

        $this->info("Found {$locations->count()} locations to geocode.");

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