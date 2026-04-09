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

        $data = DB::table('ips')
                ->leftJoin('servers', 'servers.id', '=', 'ips.service_id')
                ->leftJoin('shared_hosting', 'shared_hosting.id', '=', 'ips.service_id')
                ->leftJoin('reseller_hosting', 'reseller_hosting.id', '=', 'ips.service_id')
                ->leftJoin('seedboxes', 'seedboxes.id', '=', 'ips.service_id')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where(function ($query) {
                    $query->whereNotNull('servers.id')
                        ->orWhereNotNull('shared_hosting.id')
                        ->orWhereNotNull('reseller_hosting.id')
                        ->orWhereNotNull('seedboxes.id');
                })
                ->select(
                    'ips.service_id',
                    'ips.address',
                    'ips.latitude',
                    'ips.longitude',
                    'ips.city',
                    'ips.country',
                    DB::raw("CASE
                        WHEN servers.id IS NOT NULL THEN 'Server'
                        WHEN shared_hosting.id IS NOT NULL THEN 'Shared'
                        WHEN reseller_hosting.id IS NOT NULL THEN 'Reseller'
                        WHEN seedboxes.id IS NOT NULL THEN 'Seed Box'
                    END as type"),
                    DB::raw('COALESCE(servers.hostname, shared_hosting.main_domain, reseller_hosting.main_domain, seedboxes.hostname, seedboxes.title, ips.address) as name')
                )
                ->get()
                ->map(function ($ip) {
                    return [
                        'lat' => (float) $ip->latitude,
                        'lng' => (float) $ip->longitude,
                        'ip' => $ip->address,
                        'city' => $ip->city,
                        'country' => $ip->country,
                        'type' => $ip->type,
                        'name' => $ip->name,
                        'service_id' => $ip->service_id,
                    ];
                })
                ->values();

        Cache::put('map_data', $data, $data->isEmpty() ? now()->addMinutes(5) : now()->addDay());

        return response()->json($data);
    }
}
