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
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '7:00–9:00 PM',
            'venue' => 'Jollibee Global City',
            'venue_map_url' => 'https://maps.google.com/?q=Jollibee+Global+City',
            'expires_at' => now()->addWeek(),
            'is_active' => true,
        ];
    }
}
