<?php

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('calendar'));

    $response->assertRedirect(route('login'));
});

test('the month view shows every family member\'s events in the viewer\'s timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
    $viewer = User::factory()->create(['name' => 'Alex', 'color' => '#2563eb', 'timezone' => 'America/New_York']);
    $sam = User::factory()->create(['name' => 'Sam', 'color' => '#db2777']);
    $event = Event::factory()->for($sam)->create([
        'title' => 'Piano lesson',
        'starts_at' => '2026-11-03 21:00:00',
        'ends_at' => '2026-11-03 22:30:00',
    ]);

    $response = $this->actingAs($viewer)->get(route('calendar'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Calendar')
        ->where('view', 'month')
        ->where('date', '2026-11-02')
        ->where('today', '2026-11-02')
        ->where('rangeStart', '2026-11-01')
        ->where('rangeEnd', '2026-12-06')
        ->has('events', 1)
        ->where('events.0', [
            'id' => $event->id,
            'occurrence_id' => $event->id.'@20261103T210000Z',
            'user_id' => $sam->id,
            'title' => 'Piano lesson',
            'notes' => $event->notes,
            'starts_at' => '2026-11-03T16:00',
            'ends_at' => '2026-11-03T17:30',
            'series_starts_at' => '2026-11-03T16:00',
            'series_ends_at' => '2026-11-03T17:30',
            'recurrence_rule' => null,
            'recurrence' => null,
        ])
        ->where('members', [
            ['id' => $viewer->id, 'name' => 'Alex', 'color' => '#2563eb'],
            ['id' => $sam->id, 'name' => 'Sam', 'color' => '#db2777'],
        ])
    );
});

test('the week view only includes events overlapping that week in the viewer\'s timezone', function () {
    $viewer = User::factory()->create(['timezone' => 'America/New_York']);
    $events = Event::factory()->for($viewer)->sequence(
        ['title' => 'Saturday night before', 'starts_at' => '2026-11-01 02:00:00', 'ends_at' => '2026-11-01 03:00:00'],
        ['title' => 'Just after midnight Sunday', 'starts_at' => '2026-11-01 04:30:00', 'ends_at' => '2026-11-01 05:30:00'],
        ['title' => 'Late Saturday', 'starts_at' => '2026-11-08 04:00:00', 'ends_at' => '2026-11-08 04:30:00'],
        ['title' => 'Midnight the next Sunday', 'starts_at' => '2026-11-08 05:00:00', 'ends_at' => '2026-11-08 06:00:00'],
    )->count(4)->create();

    $response = $this->actingAs($viewer)->get(route('calendar', ['view' => 'week', 'date' => '2026-11-04']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Calendar')
        ->where('view', 'week')
        ->where('rangeStart', '2026-11-01')
        ->where('rangeEnd', '2026-11-08')
        ->has('events', 2)
        ->where('events.0.title', 'Just after midnight Sunday')
        ->where('events.0.starts_at', '2026-11-01T00:30')
        ->where('events.1.title', 'Late Saturday')
        ->where('events.1.starts_at', '2026-11-07T23:00')
    );
});

test('a malformed view or date falls back to the current month', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00'));
    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->get(route('calendar', ['view' => 'year', 'date' => 'not-a-date']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Calendar')
        ->where('view', 'month')
        ->where('date', '2026-11-18')
        ->where('rangeStart', '2026-11-01')
    );
});
