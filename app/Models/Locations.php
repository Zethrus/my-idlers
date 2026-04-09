<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

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
        $result = self::geocodeResult($this->name);

        if (!$result['success']) {
            return false;
        }

        $this->update($result['coordinates']);

        return true;
    }

    public static function geocode(string $locationName): ?array
    {
        $result = self::geocodeResult($locationName);

        return $result['success'] ? $result['coordinates'] : null;
    }

    public static function geocodeResult(string $locationName): array
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

        $lastError = 'Unknown geocoding error.';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.name', 'my-idlers') . ' location-geocoder',
                    'Accept-Language' => 'en',
                    'Accept' => 'application/json',
                ])->timeout(15)->get('https://nominatim.openstreetmap.org/search', $query);

                if (!$response->ok()) {
                    $lastError = 'HTTP ' . $response->status() . ' from geocoding service.';

                    if (in_array($response->status(), [403, 429, 500, 502, 503, 504], true) && $attempt < 3) {
                        sleep($attempt * 2);
                        continue;
                    }

                    return self::failedGeocode($lastError, $response->body());
                }

                $results = $response->json();

                if (!is_array($results)) {
                    $lastError = 'Geocoding service returned invalid JSON.';

                    if ($attempt < 3) {
                        sleep($attempt * 2);
                        continue;
                    }

                    return self::failedGeocode($lastError, $response->body());
                }

                $match = $results[0] ?? null;

                if (!is_array($match)) {
                    return self::failedGeocode('No matching place found for location name.');
                }

                if (!isset($match['lat'], $match['lon'])) {
                    return self::failedGeocode('Geocoding result missing latitude/longitude fields.');
                }

                $address = is_array($match['address'] ?? null) ? $match['address'] : [];

                return [
                    'success' => true,
                    'coordinates' => [
                        'latitude' => (float) $match['lat'],
                        'longitude' => (float) $match['lon'],
                        'geo_city' => self::extractCity($address),
                        'geo_country' => $address['country'] ?? null,
                        'geo_display_name' => $match['display_name'] ?? null,
                    ],
                    'error' => null,
                ];
            } catch (Throwable $throwable) {
                $lastError = $throwable->getMessage();

                if ($attempt < 3) {
                    sleep($attempt * 2);
                    continue;
                }
            }
        }

        return self::failedGeocode('Geocoding request failed after retries: ' . $lastError);
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

    private static function failedGeocode(string $error, ?string $body = null): array
    {
        $message = $error;

        if ($body !== null) {
            $snippet = trim(substr(strip_tags($body), 0, 180));
            if ($snippet !== '') {
                $message .= ' Response: ' . $snippet;
            }
        }

        return [
            'success' => false,
            'coordinates' => null,
            'error' => $message,
        ];
    }

    private static function forgetCaches(): void
    {
        Cache::forget('locations');
        Cache::forget('map_data');
    }
}
