<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'ico' => fake()->numerify('########'), // Generates an 8-digit number
            'sector' => fake()->randomElement(['Software', 'Hardware', 'Marketing', 'Education']),
            'description' => fake()->paragraph(),
            'website' => fake()->url(),
            'status' => 'active',
        ];
    }
}
