<?php

namespace Miguilim\Helpers;

use InvalidArgumentException;

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
     * @return self
     */
    public static function find($id, $columns = ['*'])
    {
        $config = config('ip_address');

        if (is_array($id) || $id instanceof Arrayable) {
            throw new InvalidArgumentException('IpAddress::find() expects a string as the first parameter.');
        }

        $result = self::whereKey($id)->first($columns);

        if ($result) {
            if ($result->updated_at < now()->subMinutes($config['cache_duration'])) {
                return self::updateOrCreateIpAddress($id) ?? $result;
            }

            return $result;
        }

        return self::updateOrCreateIpAddress($id) ?? new self(['ip_address' => $id]);
    }

    /**
     * Create a new element with current request ip address.
     */
    public static function currentRequest(): self
    {
        return self::find(optional(request())->ip());
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
                $result = self::proxyCheckRequest($ipAddress, $config);
            } elseif ($config['driver'] === 'ipregistry') {
                $result = self::ipRegistryRequest($ipAddress, $config);
            } elseif ($config['driver'] === 'ipqualityscore') {
                $result = self::ipQualityScoreRequest($ipAddress, $config);
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

        return self::updateOrCreate(['ip_address' => $ipAddress], $result);
    }

    /**
     * Make request to proxyCheck service.
     */
    protected static function proxyCheckRequest(string $ip, array $config): ?array
    {
        $response = Http::timeout(5)->get("https://proxycheck.io/v2/{$ip}?key={$config['key']}&vpn=1&asn=1&risk=1")->json();

        if (! isset($response[$ip])) { // Cannot check status === 'ok', since status is not always ok, even when the request is successful
            return null;
        }

        $response = $response[$ip];

        return [
            'ip_address' => $ip,
            'asn' => (int) str_replace('AS', '', $response['asn'] ?? '-1'),
            'continent' => $response['continent'],
            'country' => $response['country'] ?? 'Unknown',
            'country_code' => $response['isocode'] ?? 'XX',
            'region' => $response['region'] ?? 'Unknown',
            'region_code' => $response['regioncode'] ?? '00',
            'city' => $response['city'] ?? 'Unknown',
            'latitude' => $response['latitude'] ?? 0,
            'longitude' => $response['longitude'] ?? 0,
            'risk' => (int) ($response['risk'] ?? '100'),
            'block' => $response['proxy'] === 'yes',
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
            'risk' => ($isBlock) ? 100 : 0,
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
