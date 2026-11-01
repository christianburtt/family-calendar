<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\CalendarUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Burtt Family', 'The Smiths', 'Willow Street', 'Grandma\'s House',
            'Beach House', 'Sunday Crew', 'The Hendersons', 'Maple Avenue',
        ]);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Calendar::unusedSlug($name),
            'join_code' => Calendar::unusedJoinCode(),
        ];
    }

    /**
     * Put the given person in charge of the calendar.
     */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn (): array => ['owner_id' => $owner->id]);
    }

    /**
     * Add an approved member, creating their account when none is given.
     */
    public function withMember(?User $user = null, string $role = CalendarUser::ROLE_MEMBER): static
    {
        return $this->afterCreating(function (Calendar $calendar) use ($user, $role): void {
            $calendar->members()->attach($user ?? User::factory()->create(), [
                'role' => $role,
                'status' => CalendarUser::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * Add someone whose request is still waiting for approval.
     */
    public function withPendingMember(?User $user = null): static
    {
        return $this->afterCreating(function (Calendar $calendar) use ($user): void {
            $calendar->members()->attach($user ?? User::factory()->create(), [
                'role' => CalendarUser::ROLE_MEMBER,
                'status' => CalendarUser::STATUS_PENDING,
            ]);
        });
    }
}
