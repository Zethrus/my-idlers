<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Locations extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'latitude', 'longitude', 'geo_city', 'geo_country', 'geo_display_name'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $table = 'locations';

    protected $keyType = 'int';

    protected static function booted(): void
    {
        static::saved(function () {
            self::forgetCaches();
        });

        static::deleted(function () {
            self::forgetCaches();
        });
    }

    public static function allLocations(): array
    {
        return Cache::remember("locations", now()->addMonth(1), function () {
            return self::orderBy('name')->get()->toArray();
        });
    }

    public function syncCoordinates(): bool
    {
        $coordinates = self::geocode($this->name);

        if ($coordinates === null) {
            return false;
        }

        $this->update($coordinates);

        return true;
    }

    public static function geocode(string $locationName): ?array
    {
        $query = [
            'q' => $locationName,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 1,
        ];

        $email = config('mail.from.address');
        if (!empty($email)) {
            $query['email'] = $email;
        }

        $response = Http::withHeaders([
            'User-Agent' => config('app.name', 'my-idlers') . ' location-geocoder',
            'Accept-Language' => 'en',
        ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', $query);

        if (!$response->ok()) {
            return null;
        }

        $results = $response->json();
        $match = $results[0] ?? null;

        if (empty($match['lat']) || empty($match['lon'])) {
            return null;
        }

        $address = $match['address'] ?? [];

        return [
            'latitude' => (float) $match['lat'],
            'longitude' => (float) $match['lon'],
            'geo_city' => self::extractCity($address),
            'geo_country' => $address['country'] ?? null,
            'geo_display_name' => $match['display_name'] ?? null,
        ];
    }

    private static function extractCity(array $address): ?string
    {
        foreach (['city', 'town', 'municipality', 'village', 'hamlet', 'county', 'state'] as $field) {
            if (!empty($address[$field])) {
                return $address[$field];
            }
        }

        return null;
    }

    private static function forgetCaches(): void
    {
        Cache::forget('locations');
        Cache::forget('map_data');
    }
}
