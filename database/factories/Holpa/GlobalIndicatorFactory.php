<?php

namespace Database\Factories\Holpa;

use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalIndicator>
 */
class GlobalIndicatorFactory extends Factory
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
            'type' => $this->faker->randomElement(['Core (required) indicators', 'Optional indicators']),
            'theme_id' => $this->faker->randomElement(Theme::all()->pluck('id')->toArray()),
        ];
    }
}
