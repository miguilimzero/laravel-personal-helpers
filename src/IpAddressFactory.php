<?php

namespace Miguilim\Helpers;

use Illuminate\Database\Eloquent\Factories\Factory;

class IpAddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = IpAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address'   => $this->faker->ipv4(),
            'asn'          => $this->faker->randomNumber(4, true),
            'continent'    => 'world',
            'country'      => $this->faker->country(),
            'country_code' => $this->faker->countryCode(),
            'region'       => $this->faker->state(),
            'region_code'  => $this->faker->stateAbbr(),
            'city'         => $this->faker->city(),
            'latitude'     => $this->faker->latitude(),
            'longitude'    => $this->faker->longitude(),
            'risk'         => $this->faker->randomNumber(2),
            'block'        => $this->faker->boolean(),
            'driver'       => 'factory',
        ];
    }
}
