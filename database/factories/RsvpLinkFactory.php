<?php

namespace Database\Factories;

use App\Models\RsvpLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RsvpLink> */
class RsvpLinkFactory extends Factory
{
    protected $model = RsvpLink::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'expires_at' => now()->addWeek(),
            'is_active' => true,
        ];
    }
}
