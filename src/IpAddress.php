<?php

namespace Miguilim\Helpers;

use InvalidArgumentException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

use Illuminate\Database\Eloquent\Model;

class IpAddress extends Model
{
    use HasFactory;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ip_address';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ip_address',
        'asn',
        'continent',
        'country',
        'country_code',
        'region',
        'region_code',
        'city',
        'latitude',
        'longitude',
        'risk',
        'block',
        'driver',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'block' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return IpAddressFactory::new();
    }

    /**
     * Create a new element.
     *
     * @param mixed $id
     * @param mixed $columns
     *
     * @return static
     */
    public static function find($id, $columns = ['*'])
    {
        $config = config('ip_address');

        if (is_array($id) || $id instanceof Arrayable) {
            throw new InvalidArgumentException('IpAddress::find() expects a string as the first parameter.');
        }

        $result = static::whereKey($id)->first($columns);

        if ($result) {
            if ($result->updated_at < now()->subMinutes($config['cache_duration'])) {
                return static::updateOrCreateIpAddress($id) ?? $result;
            }

            return $result;
        }

        return static::updateOrCreateIpAddress($id) ?? new static(['ip_address' => $id]);
    }

    /**
     * Create a new element with current request ip address.
     */
    public static function currentRequest(): static
    {
        return static::find(optional(request())->ip());
    }

    /**
     * Get country code from a request object using CF or fallback to IP query.
     */
    public static function getCountryCodeFromRequest(Request $request): string
    {
        $cloudflareHeader = $request->header('CF-IPCountry');

        if ($cloudflareHeader !== null && is_string($cloudflareHeader)) {
            return $cloudflareHeader;
        }

        return static::find($request->ip())->country_code ?? 'XX';
    }

    /**
     * Get location string attribute.
     */
    public function getLocationAttribute(): ?string
    {
        if (! $this->country) {
            return null;
        }

        return trim("{$this->country}, {$this->city} - {$this->region}");
    }

    /**
     * Update or create IP address.
     */
    protected static function updateOrCreateIpAddress(string $ipAddress)
    {
        $config = config('ip_address');

        if ($config['key']) {
            if ($config['driver'] === 'proxycheck') {
                $result = static::proxyCheckRequest($ipAddress, $config);
            } elseif ($config['driver'] === 'ipregistry') {
                $result = static::ipRegistryRequest($ipAddress, $config);
            } elseif ($config['driver'] === 'ipqualityscore') {
                $result = static::ipQualityScoreRequest($ipAddress, $config);
            } else {
                throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
            }
        } else {
            $result = false;
        }

        if (! $result) {
            return null;
        }

        // Ensure that it will not try to create with an existing row

        return static::updateOrCreate(['ip_address' => $ipAddress], $result);
    }

    /**
     * Make request to proxyCheck service.
     */
    protected static function proxyCheckRequest(string $ip, array $config): ?array
    {
        $response = Http::timeout(5)->get("https://proxycheck.io/v3/{$ip}?key={$config['key']}")->json();

        if (! isset($response[$ip])) { // Cannot check status === 'ok', since status is not always ok, even when the request is successful
            return null;
        }

        $response = $response[$ip];

        $isBlock = $response['detections']['proxy']
            || $response['detections']['vpn']
            || $response['detections']['compromised']
            || $response['detections']['scraper']
            || $response['detections']['tor']
            || $response['detections']['hosting']
            || $response['detections']['anonymous'];

        return [
            'ip_address' => $ip,
            'asn' => (int) str_replace('AS', '', $response['network']['asn']),
            'continent' => $response['location']['continent_code'],
            'country' => $response['location']['country_name'],
            'country_code' => $response['location']['country_code'],
            'region' => $response['location']['region_name'],
            'region_code' => $response['location']['region_code'],
            'city' => $response['location']['city_name'],
            'latitude' => $response['location']['latitude'],
            'longitude' => $response['location']['longitude'],
            'risk' => $response['detections']['risk'],
            'block' => $isBlock,
            'driver' => 'proxycheck',
        ];
    }

    /**
     * Make request to ipRegistry service.
     */
    protected static function ipRegistryRequest(string $ip, array $config): ?array
    {
        $response = Http::timeout(5)->get("https://api.ipregistry.co/{$ip}?key={$config['key']}")->json();

        if (isset($response['code']) || ! isset($response['ip'])) {
            return null;
        }

        $isBlock = false;
        foreach ($response['security'] as $value) {
            if ($value) {
                $isBlock = true;
                break;
            }
        }

        return [
            'ip_address' => $ip,
            'asn' => $response['connection']['asn'],
            'continent' => $response['location']['continent']['continent'],
            'country' => $response['location']['country']['name'],
            'country_code' => $response['location']['country']['code'],
            'region' => $response['location']['region']['name'],
            'region_code' => $response['location']['region']['code'],
            'city' => $response['location']['city'],
            'latitude' => $response['location']['latitude'],
            'longitude' => $response['location']['longitude'],
            'risk' => $isBlock ? 100 : 0,
            'block' => $isBlock,
            'driver' => 'ipregistry',
        ];
    }

    /**
     * Make request to ipQualityScore service.
     */
    protected static function ipQualityScoreRequest(string $ip, array $config): ?array
    {
        $response = Http::timeout(5)->get("https://www.ipqualityscore.com/api/json/ip/{$config['key']}/{$ip}")->json();

        if (! isset($response['success']) || $response['success'] !== true) {
            return null;
        }

        $isBlock = false;
        if ($response['is_crawler']) {
            $isBlock = true;
        }
        if ($response['proxy'] || $response['vpn'] || $response['tor']) {
            $isBlock = true;
        }
        if ($response['active_vpn'] || $response['active_tor'] || $response['recent_abuse']) {
            $isBlock = true;
        }
        if ($response['bot_status'] || $response['fraud_score'] >= 75) {
            $isBlock = true;
        }

        return [
            'ip_address' => $ip,
            'asn' => $response['asn'],
            'continent' => 'Unknown',
            'country' => locale_get_display_region("-{$response['country_code']}", 'en'),
            'country_code' => $response['country_code'],
            'region' => $response['region'],
            'region_code' => 'XX',
            'city' => $response['city'],
            'latitude' => $response['latitude'],
            'longitude' => $response['longitude'],
            'risk' => $response['fraud_score'],
            'block' => $isBlock,
            'driver' => 'ipqualityscore',
        ];
    }
}
