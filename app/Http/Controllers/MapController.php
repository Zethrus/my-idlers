<?php

namespace App\Http\Controllers;

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
        $cachedData = Cache::get('map_data');

        if ($cachedData !== null) {
            return response()->json($cachedData);
        }

        $primaryIps = DB::table('ips')
            ->select('service_id', DB::raw('MIN(address) as address'))
            ->groupBy('service_id');

        $data = $this->serverRows($primaryIps)
            ->concat($this->sharedRows($primaryIps))
            ->concat($this->resellerRows($primaryIps))
            ->concat($this->seedboxRows($primaryIps))
            ->map(function ($service) {
                return [
                    'lat' => (float) $service->latitude,
                    'lng' => (float) $service->longitude,
                    'ip' => $service->ip,
                    'location' => $service->location,
                    'city' => $service->city,
                    'country' => $service->country,
                    'type' => $service->type,
                    'name' => $service->name,
                    'service_id' => $service->service_id,
                ];
            })
            ->values();

        Cache::put('map_data', $data, $data->isEmpty() ? now()->addMinutes(5) : now()->addDay());

        return response()->json($data);
    }

    private function serverRows($primaryIps)
    {
        return DB::table('servers')
            ->join('locations', 'locations.id', '=', 'servers.location_id')
            ->leftJoinSub($primaryIps, 'primary_ips', function ($join) {
                $join->on('primary_ips.service_id', '=', 'servers.id');
            })
            ->whereNotNull('locations.latitude')
            ->whereNotNull('locations.longitude')
            ->select(
                'servers.id as service_id',
                'servers.hostname as name',
                'locations.name as location',
                'locations.geo_city as city',
                'locations.geo_country as country',
                'locations.latitude',
                'locations.longitude',
                DB::raw("'Server' as type"),
                'primary_ips.address as ip'
            )
            ->get();
    }

    private function sharedRows($primaryIps)
    {
        return DB::table('shared_hosting')
            ->join('locations', 'locations.id', '=', 'shared_hosting.location_id')
            ->leftJoinSub($primaryIps, 'primary_ips', function ($join) {
                $join->on('primary_ips.service_id', '=', 'shared_hosting.id');
            })
            ->whereNotNull('locations.latitude')
            ->whereNotNull('locations.longitude')
            ->select(
                'shared_hosting.id as service_id',
                'shared_hosting.main_domain as name',
                'locations.name as location',
                'locations.geo_city as city',
                'locations.geo_country as country',
                'locations.latitude',
                'locations.longitude',
                DB::raw("'Shared' as type"),
                'primary_ips.address as ip'
            )
            ->get();
    }

    private function resellerRows($primaryIps)
    {
        return DB::table('reseller_hosting')
            ->join('locations', 'locations.id', '=', 'reseller_hosting.location_id')
            ->leftJoinSub($primaryIps, 'primary_ips', function ($join) {
                $join->on('primary_ips.service_id', '=', 'reseller_hosting.id');
            })
            ->whereNotNull('locations.latitude')
            ->whereNotNull('locations.longitude')
            ->select(
                'reseller_hosting.id as service_id',
                'reseller_hosting.main_domain as name',
                'locations.name as location',
                'locations.geo_city as city',
                'locations.geo_country as country',
                'locations.latitude',
                'locations.longitude',
                DB::raw("'Reseller' as type"),
                'primary_ips.address as ip'
            )
            ->get();
    }

    private function seedboxRows($primaryIps)
    {
        return DB::table('seedboxes')
            ->join('locations', 'locations.id', '=', 'seedboxes.location_id')
            ->leftJoinSub($primaryIps, 'primary_ips', function ($join) {
                $join->on('primary_ips.service_id', '=', 'seedboxes.id');
            })
            ->whereNotNull('locations.latitude')
            ->whereNotNull('locations.longitude')
            ->select(
                'seedboxes.id as service_id',
                DB::raw('COALESCE(seedboxes.hostname, seedboxes.title) as name'),
                'locations.name as location',
                'locations.geo_city as city',
                'locations.geo_country as country',
                'locations.latitude',
                'locations.longitude',
                DB::raw("'Seed Box' as type"),
                'primary_ips.address as ip'
            )
            ->get();
    }
}
