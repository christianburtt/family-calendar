<?php

use App\Models\Event;
use App\Models\User;

describe('store', function () {
    test('guests cannot add events', function () {
        $response = $this->post(route('events.store'), [
            'title' => 'Soccer practice',
            'starts_at' => '2026-11-03T16:00',
            'ends_at' => '2026-11-03T17:00',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount(Event::class, 0);
    });

    test('an event is added to the user\'s calendar, converted from their timezone to UTC', function () {
        $user = User::factory()->create(['timezone' => 'America/New_York']);

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'notes' => 'Bring shin guards',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:30',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendar'));

        $this->assertDatabaseHas(Event::class, [
            'user_id' => $user->id,
            'title' => 'Soccer practice',
            'notes' => 'Bring shin guards',
            'starts_at' => '2026-11-03 21:00:00',
            'ends_at' => '2026-11-03 22:30:00',
        ]);
    });

    test('a title, start and end are required', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), []);

        $response->assertSessionHasErrors([
            'title' => 'The title field is required.',
            'starts_at' => 'The starts at field is required.',
            'ends_at' => 'The ends at field is required.',
        ]);
        $this->assertDatabaseCount(Event::class, 0);
    });

    test('an event that does not end after it starts is rejected', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->post(route('events.store'), [
                'title' => 'Soccer practice',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T16:00',
            ]);

        $response->assertSessionHasErrors(['ends_at' => 'The event must end after it starts.']);
        $this->assertDatabaseCount(Event::class, 0);
    });
});

describe('update', function () {
    test('a user can update their own event', function () {
        $user = User::factory()->create(['timezone' => 'Europe/London']);
        $event = Event::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->patch(route('events.update', $event), [
                'title' => 'Dentist',
                'notes' => null,
                'starts_at' => '2026-07-01T09:00',
                'ends_at' => '2026-07-01T09:45',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendar'));

        $event->refresh();

        expect($event->title)->toBe('Dentist');
        expect($event->notes)->toBeNull();
        expect($event->starts_at->toDateTimeString())->toBe('2026-07-01 08:00:00');
        expect($event->ends_at->toDateTimeString())->toBe('2026-07-01 08:45:00');
    });

    test('a user cannot update another family member\'s event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create(['title' => 'Piano lesson']);

        $response = $this
            ->actingAs($user)
            ->patch(route('events.update', $event), [
                'title' => 'Hijacked',
                'starts_at' => '2026-11-03T16:00',
                'ends_at' => '2026-11-03T17:00',
            ]);

        $response->assertForbidden();

        expect($event->refresh()->title)->toBe('Piano lesson');
    });
});

describe('destroy', function () {
    test('a user can delete their own event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->from(route('calendar'))
            ->delete(route('events.destroy', $event));

        $response->assertRedirect(route('calendar'));

        $this->assertModelMissing($event);
    });

    test('a user cannot delete another family member\'s event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('events.destroy', $event));

        $response->assertForbidden();

        $this->assertModelExists($event);
    });
});
