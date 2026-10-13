<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'parcel_reference' => fake()->bothify('PARCEL-####'),
            'survey_plan_reference' => fake()->bothify('PLAN-####'),
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'courses' => [
                ['bearing' => 0, 'distance' => 100],
                ['bearing' => 90, 'distance' => 100],
                ['bearing' => 180, 'distance' => 100],
                ['bearing' => 270, 'distance' => 100],
            ],
            'boundary' => [[-122.08, 37.42], [-122.08, 37.4209], [-122.08, 37.42]],
            'closure_error_meters' => 0,
        ];
    }
}
