<?php

namespace App\Console\Commands;

use App\Models\IPs;
use Illuminate\Console\Command;

class BackfillIpCoordinates extends Command
{
    protected $signature = 'ips:backfill-coordinates';

    protected $description = 'Backfill latitude and longitude for IPs that have been fetched but lack coordinates';

    public function handle(): int
    {
        $ips = IPs::whereNotNull('fetched_at')
            ->whereNull('latitude')
            ->get();

        if ($ips->isEmpty()) {
            $this->info('No IPs need coordinate backfilling.');
            return Command::SUCCESS;
        }

        $this->info("Found {$ips->count()} IPs to backfill.");

        $bar = $this->output->createProgressBar($ips->count());
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($ips as $ip) {
            $result = IPs::getUpdateIpInfo($ip);

            if ($result) {
                $updated++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("Failed to fetch info for {$ip->address}");
            }

            $bar->advance();

            // Rate limit: 1 request per second to respect ipwhois.app limits
            if (!$ips->last()->is($ip)) {
                sleep(1);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
