<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::today()
            ->addDays(fake()->numberBetween(-7, 14))
            ->setTime(fake()->numberBetween(7, 19), fake()->randomElement([0, 30]));

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement([
                'Soccer practice', 'Piano lesson', 'Dentist', 'Book club', 'Swim lessons',
                'Grocery run', 'Date night', 'Yoga', 'Haircut', 'Work dinner', 'Playdate',
            ]),
            'notes' => fake()->optional()->sentence(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(fake()->randomElement([30, 60, 90, 120])),
        ];
    }

    /**
     * Make the event repeat using the given RRULE.
     */
    public function recurring(string $rule = 'FREQ=WEEKLY'): static
    {
        return $this->state(fn (): array => ['recurrence_rule' => $rule]);
    }
}
