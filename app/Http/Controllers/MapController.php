<?php

namespace App\Http\Controllers;

use App\Models\IPs;
use App\Models\Server;
use App\Models\Shared;
use App\Models\Reseller;
use App\Models\SeedBoxes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    public function data()
    {
        $data = Cache::remember('map_data', now()->addMonth(1), function () {
            return DB::table('ips')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('ips.service_id', 'ips.address', 'ips.latitude', 'ips.longitude', 'ips.city', 'ips.country')
                ->get()
                ->map(function ($ip) {
                    $service = null;
                    $type = null;

                    if ($found = Server::where('id', $ip->service_id)->first()) {
                        $type = 'Server';
                        $service = $found;
                    } elseif ($found = Shared::where('id', $ip->service_id)->first()) {
                        $type = 'Shared';
                        $service = $found;
                    } elseif ($found = Reseller::where('id', $ip->service_id)->first()) {
                        $type = 'Reseller';
                        $service = $found;
                    } elseif ($found = SeedBoxes::where('id', $ip->service_id)->first()) {
                        $type = 'Seed Box';
                        $service = $found;
                    }

                    return [
                        'lat' => (float) $ip->latitude,
                        'lng' => (float) $ip->longitude,
                        'ip' => $ip->address,
                        'city' => $ip->city,
                        'country' => $ip->country,
                        'type' => $type,
                        'name' => $service ? ($service->hostname ?? $service->main_domain ?? $service->title ?? $service->name ?? $ip->address) : $ip->address,
                        'service_id' => $ip->service_id,
                    ];
                })
                ->filter(fn ($item) => $item['type'] !== null)
                ->values();
        });

        return response()->json($data);
    }
}
