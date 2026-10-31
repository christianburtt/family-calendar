<?php

use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('storing a recurrence', function () {
    test('a weekly event is saved as an RRULE on the series', function () {
        $user = User::factory()->create(['timezone' => 'America/New_York']);

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'weekly',
                'repeat_interval' => 1,
                'repeat_on' => ['TU', 'TH'],
                'repeat_ends' => 'never',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('calendar'));

        expect(Event::sole()->recurrence_rule)->toBe('FREQ=WEEKLY;BYDAY=TU,TH');
    });

    test('an event that ends on a date stores a UTC until', function () {
        $user = User::factory()->create(['timezone' => 'America/New_York']);

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Book club',
                'starts_at' => '2026-11-03T19:00',
                'ends_at' => '2026-11-03T20:00',
                'repeats' => 'monthly',
                'repeat_interval' => 1,
                'repeat_ends' => 'on',
                'repeat_until' => '2026-12-31',
            ])
            ->assertSessionHasNoErrors();

        expect(Event::sole()->recurrence_rule)->toBe('FREQ=MONTHLY;UNTIL=20270101T045959Z');
    });

    test('an event that ends after a number of occurrences stores a count', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Yoga',
                'starts_at' => '2026-11-03T07:00',
                'ends_at' => '2026-11-03T08:00',
                'repeats' => 'daily',
                'repeat_interval' => 2,
                'repeat_ends' => 'after',
                'repeat_count' => 5,
            ])
            ->assertSessionHasNoErrors();

        expect(Event::sole()->recurrence_rule)->toBe('FREQ=DAILY;INTERVAL=2;COUNT=5');
    });

    test('weekly recurrence requires at least one day', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'weekly',
                'repeat_interval' => 1,
                'repeat_on' => [],
                'repeat_ends' => 'never',
            ])
            ->assertSessionHasErrors([
                'repeat_on' => 'Pick at least one day to repeat on.',
            ]);

        $this->assertDatabaseCount(Event::class, 0);
    });

    test('a repeat end date or count is required when chosen', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'weekly',
                'repeat_interval' => 1,
                'repeat_on' => ['TU'],
                'repeat_ends' => 'on',
            ])
            ->assertSessionHasErrors([
                'repeat_until' => 'Pick the date the event stops repeating.',
            ]);

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'weekly',
                'repeat_interval' => 1,
                'repeat_on' => ['TU'],
                'repeat_ends' => 'after',
            ])
            ->assertSessionHasErrors([
                'repeat_count' => 'Choose how many times the event repeats.',
            ]);
    });

    test('an unknown weekday is rejected', function () {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'weekly',
                'repeat_interval' => 1,
                'repeat_on' => ['FUNDAY'],
                'repeat_ends' => 'never',
            ])
            ->assertSessionHasErrors('repeat_on.0');
    });
});

describe('showing a recurrence', function () {
    test('the calendar expands a weekly series into one event per occurrence', function () {
        $viewer = User::factory()->create(['timezone' => 'America/New_York']);

        $event = Event::factory()->for($viewer)->recurring('FREQ=WEEKLY;BYDAY=TU,TH')->create([
            'title' => 'Soccer practice',
            'starts_at' => '2026-11-03 21:00:00',
            'ends_at' => '2026-11-03 22:00:00',
        ]);

        $response = $this->actingAs($viewer)->get(route('calendar', [
            'view' => 'week',
            'date' => '2026-11-01',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)
            ->where('events.0.title', 'Soccer practice')
            ->where('events.0.starts_at', '2026-11-03T16:00')
            ->where('events.0.occurrence_id', $event->id.'@20261103T210000Z')
            ->where('events.0.recurrence.frequency', 'weekly')
            ->where('events.0.recurrence.days', ['TU', 'TH'])
            ->where('events.1.starts_at', '2026-11-05T16:00')
            ->where('events.1.occurrence_id', $event->id.'@20261105T210000Z')
        );
    });

    test('the series start is sent alongside each occurrence for editing', function () {
        $viewer = User::factory()->create(['timezone' => 'America/New_York']);

        Event::factory()->for($viewer)->recurring('FREQ=WEEKLY;BYDAY=TU')->create([
            'starts_at' => '2026-11-03 21:00:00',
            'ends_at' => '2026-11-03 22:00:00',
        ]);

        $response = $this->actingAs($viewer)->get(route('calendar', [
            'view' => 'week',
            'date' => '2026-11-10',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.starts_at', '2026-11-10T16:00')
            ->where('events.0.series_starts_at', '2026-11-03T16:00')
        );
    });

    test('a one-off event is still returned once', function () {
        $viewer = User::factory()->create();

        Event::factory()->for($viewer)->create([
            'starts_at' => '2026-11-03 16:00:00',
            'ends_at' => '2026-11-03 17:00:00',
        ]);

        $response = $this->actingAs($viewer)->get(route('calendar', [
            'view' => 'week',
            'date' => '2026-11-01',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.recurrence', null)
            ->where('events.0.recurrence_rule', null)
        );
    });
});

describe('changing a recurrence', function () {
    test('updating the series replaces the rule', function () {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->recurring('FREQ=WEEKLY;BYDAY=TU')->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->patch(route('events.update', $event), [
                'title' => $event->title,
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'daily',
                'repeat_interval' => 3,
                'repeat_ends' => 'never',
            ])
            ->assertSessionHasNoErrors();

        expect($event->refresh()->recurrence_rule)->toBe('FREQ=DAILY;INTERVAL=3');
    });

    test('turning recurrence off clears the rule', function () {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->recurring('FREQ=WEEKLY;BYDAY=TU')->create();

        $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->patch(route('events.update', $event), [
                'title' => $event->title,
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
                'repeats' => 'never',
            ])
            ->assertSessionHasNoErrors();

        expect($event->refresh()->recurrence_rule)->toBeNull();
    });
});
