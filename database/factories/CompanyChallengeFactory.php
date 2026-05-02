<?php

namespace Database\Factories;

use App\Models\CompanyChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyChallenge>
 */
class CompanyChallengeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'technical_spec' => fake()->paragraphs(3, true),
            'budget' => fake()->randomFloat(2, 500, 5000),
            'status' => 'published',
            // Note: call_id and company_id should be provided when calling the factory
            // or handled via relationships in your Seeder.
        ];
    }
}
