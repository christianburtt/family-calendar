<?php

namespace Database\Factories\Medical;

use App\Medical\Models\Dosar;
use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dosar>
 */
class DosarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_id' => Calendar::factory(),
            'name' => fake()->randomElement([
                'Maria', 'Andrei', 'Elena', 'Mihai', 'Ioana', 'Cristian', 'Sofia',
            ]),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-1 year'),
            'color' => fake()->randomElement(['#2563eb', '#db2777', '#16a34a', '#ea580c', '#7c3aed']),
            'created_by_id' => User::factory(),
        ];
    }

    /**
     * File the folder in the given household.
     */
    public function onCalendar(Calendar $calendar): static
    {
        return $this->state(fn (): array => ['calendar_id' => $calendar->id]);
    }

    /**
     * Name the person the folder belongs to.
     */
    public function forPerson(string $name): static
    {
        return $this->state(fn (): array => ['name' => $name]);
    }
}
