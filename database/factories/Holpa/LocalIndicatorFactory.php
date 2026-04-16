<?php

namespace Database\Factories\Holpa;

use App\Models\Holpa\Domain;
use App\Models\Holpa\GlobalIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalIndicator>
 */
class LocalIndicatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence,
            'team_id' => null,
            'domain_id' => $this->faker->randomElement(Domain::all()->pluck('id')->toArray()),
            'global_indicator_id' => null,
        ];
    }
}
