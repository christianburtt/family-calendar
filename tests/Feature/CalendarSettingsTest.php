<?php

use App\Models\Calendar;
use App\Models\CalendarUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('creating a calendar', function () {
    test('guests are sent to the login page', function () {
        $this->get(route('calendars.create'))->assertRedirect(route('login'));
        $this->post(route('calendars.store'), ['name' => 'Burtt Family'])->assertRedirect(route('login'));

        $this->assertDatabaseCount(Calendar::class, 0);
    });

    test('the create screen is shown', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('calendars.create'))
            ->assertInertia(fn (Assert $page) => $page->component('calendars/Create'));
    });

    test('the creator becomes the owner and the calendar is opened', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('calendars.store'), ['name' => 'Burtt Family'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendar'));

        $calendar = Calendar::sole();

        expect($calendar->name)->toBe('Burtt Family');
        expect($calendar->owner_id)->toBe($user->id);
        expect($calendar->slug)->toBe('burtt-family');
        expect($calendar->join_code)->toMatch('/^[a-z]+-[a-z]+-[a-z]+$/');
        expect($user->refresh()->current_calendar_id)->toBe($calendar->id);

        $this->assertDatabaseHas(CalendarUser::class, [
            'calendar_id' => $calendar->id,
            'user_id' => $user->id,
            'role' => CalendarUser::ROLE_OWNER,
            'status' => CalendarUser::STATUS_ACTIVE,
        ]);
    });

    test('a second calendar with the same name gets a different slug and code', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calendars.store'), ['name' => 'Burtt Family']);
        $this->actingAs($user)->post(route('calendars.store'), ['name' => 'Burtt Family']);

        $calendars = Calendar::query()->orderBy('id')->get();

        expect($calendars->pluck('slug')->all())->toBe(['burtt-family', 'burtt-family-2']);
        expect($calendars->pluck('join_code')->unique())->toHaveCount(2);
    });

    test('a name is required and cannot be too long', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('calendars.create'))
            ->post(route('calendars.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->actingAs($user)
            ->from(route('calendars.create'))
            ->post(route('calendars.store'), ['name' => str_repeat('a', 61)])
            ->assertSessionHasErrors(['name' => 'The name field must not be greater than 60 characters.']);

        $this->assertDatabaseCount(Calendar::class, 0);
    });
});

describe('renaming a calendar', function () {
    test('the owner can rename it', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);

        $this->actingAs($owner)
            ->from(route('calendars.members', $calendar))
            ->patch(route('calendars.update', $calendar), ['name' => 'The Burtts'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendars.members', $calendar));

        expect($calendar->refresh()->name)->toBe('The Burtts');
    });

    test('the slug stays put so existing links keep working', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $slug = $calendar->slug;

        $this->actingAs($owner)
            ->patch(route('calendars.update', $calendar), ['name' => 'The Burtts']);

        expect($calendar->refresh()->slug)->toBe($slug);
    });

    test('a plain member cannot rename it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $name = $calendar->name;

        $this->actingAs($member)
            ->patch(route('calendars.update', $calendar), ['name' => 'Hijacked'])
            ->assertForbidden();

        expect($calendar->refresh()->name)->toBe($name);
    });

    test('a stranger cannot rename it', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $name = $calendar->name;

        $this->actingAs(User::factory()->create())
            ->patch(route('calendars.update', $calendar), ['name' => 'Hijacked'])
            ->assertForbidden();

        expect($calendar->refresh()->name)->toBe($name);
    });
});

describe('switching calendars', function () {
    test('a stranger cannot switch to a calendar they are not on', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->put(route('calendars.select', $calendar))
            ->assertForbidden();

        expect($stranger->refresh()->current_calendar_id)->toBeNull();
    });

    test('someone waiting for approval cannot switch to it yet', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $applicant = User::factory()->create();
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($applicant)
            ->put(route('calendars.select', $calendar))
            ->assertForbidden();

        expect($applicant->refresh()->current_calendar_id)->toBeNull();
    });

    test('someone removed from a calendar cannot switch back to it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);

        $calendar->members()->detach($member);

        $this->actingAs($member)
            ->put(route('calendars.select', $calendar))
            ->assertForbidden();
    });
});
