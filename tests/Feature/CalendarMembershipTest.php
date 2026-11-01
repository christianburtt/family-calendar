<?php

use App\Models\Calendar;
use App\Models\CalendarUser;
use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('showing a join code', function () {
    test('guests are sent to the login page', function () {
        $this->get(route('join.show', 'brave-otter-teapot'))->assertRedirect(route('login'));
    });

    test('a known code asks the visitor to confirm before joining', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $visitor = User::factory()->create();

        $this->actingAs($visitor)
            ->get(route('join.show', $calendar->join_code))
            ->assertInertia(fn (Assert $page) => $page
                ->component('join/Show')
                ->where('code', $calendar->join_code)
                ->where('calendar', ['id' => $calendar->id, 'name' => $calendar->name])
                ->where('status', null)
            );
    });

    test('an unknown code says so without revealing whether one exists', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('join.show', 'nope-nope-nope'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('join/Show')
                ->where('calendar', null)
                ->where('status', null)
            );
    });

    test('the code is matched no matter how it is typed', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $visitor = User::factory()->create();

        $this->actingAs($visitor)
            ->get(route('join.show', strtoupper($calendar->join_code)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.id', $calendar->id)
            );
    });

    test('an existing pending request is reported back', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $visitor = User::factory()->create();
        $calendar->members()->attach($visitor, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($visitor)
            ->get(route('join.show', $calendar->join_code))
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'pending')
            );
    });
});

describe('asking to join', function () {
    test('a valid code creates a pending membership', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $visitor = User::factory()->create();

        $this->actingAs($visitor)
            ->from(route('join.show', $calendar->join_code))
            ->post(route('join.store', $calendar->join_code))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendar'));

        $this->assertDatabaseHas(CalendarUser::class, [
            'calendar_id' => $calendar->id,
            'user_id' => $visitor->id,
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);
    });

    test('asking twice does not create a second membership', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $visitor = User::factory()->create();

        $this->actingAs($visitor)->post(route('join.store', $calendar->join_code));
        $this->actingAs($visitor)->post(route('join.store', $calendar->join_code));

        expect(CalendarUser::query()->where('user_id', $visitor->id)->count())->toBe(1);
    });

    test('an unknown code is rejected with a generic message', function () {
        $this->actingAs(User::factory()->create())
            ->from(route('calendar'))
            ->post(route('join.store', 'nope-nope-nope'))
            ->assertSessionHasErrors([
                'code' => 'That code did not work. Check it and try again.',
            ]);

        $this->assertDatabaseCount(CalendarUser::class, 0);
    });

    test('someone already on the calendar is switched to it instead of asking again', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);

        $this->actingAs($member)
            ->post(route('join.store', $calendar->join_code))
            ->assertRedirect(route('calendar'));

        expect($member->refresh()->current_calendar_id)->toBe($calendar->id);
        expect(CalendarUser::query()->where('user_id', $member->id)->count())->toBe(1);
    });
});

describe('managing members', function () {
    test('the owner sees the members, the pending requests and the join code', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $applicant = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->get(route('calendars.members', $calendar))
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendars/Members')
                ->where('calendar.id', $calendar->id)
                ->where('calendar.join_code', $calendar->join_code)
                ->where('calendar.can_manage', true)
                ->where('calendar.is_owner', true)
                ->has('members', 2)
                ->has('pending', 1)
                ->where('pending.0.user_id', $applicant->id)
            );
    });

    test('an approved member can look but not manage', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);

        $this->actingAs($member)
            ->get(route('calendars.members', $calendar))
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.can_manage', false)
                ->where('calendar.is_owner', false)
            );
    });

    test('a stranger cannot see the members of a calendar they are not on', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);

        $this->actingAs(User::factory()->create())
            ->get(route('calendars.members', $calendar))
            ->assertForbidden();
    });

    test('the owner approves a request and it becomes an active member', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $applicant = User::factory()->create();
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->from(route('calendars.members', $calendar))
            ->patch(route('memberships.approve', [$calendar, $calendar->membershipFor($applicant)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calendars.members', $calendar));

        expect($calendar->membershipFor($applicant)?->isActive())->toBeTrue();
    });

    test('a plain member cannot approve anyone', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $applicant = User::factory()->create();
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($member)
            ->patch(route('memberships.approve', [$calendar, $calendar->membershipFor($applicant)]))
            ->assertForbidden();

        expect($calendar->membershipFor($applicant)?->isPending())->toBeTrue();
    });

    test('declining a request removes it', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $applicant = User::factory()->create();
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->delete(route('memberships.destroy', [$calendar, $calendar->membershipFor($applicant)]))
            ->assertRedirect();

        expect($calendar->membershipFor($applicant))->toBeNull();
    });

    test('removing a member keeps the events they added', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $event = Event::factory()->for($member)->onCalendar($calendar)->create();

        $this->actingAs($owner)
            ->delete(route('memberships.destroy', [$calendar, $calendar->membershipFor($member)]))
            ->assertRedirect();

        expect($calendar->membershipFor($member))->toBeNull();
        $this->assertModelExists($event);
    });

    test('the owner cannot be removed', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);

        $this->actingAs($owner)
            ->delete(route('memberships.destroy', [$calendar, $calendar->membershipFor($owner)]))
            ->assertForbidden();

        expect($calendar->membershipFor($owner))->not->toBeNull();
    });

    test('a membership from another calendar cannot be reached through this one', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $otherOwner = User::factory()->create();
        $other = calendarFor($otherOwner);

        $this->actingAs($owner)
            ->delete(route('memberships.destroy', [$calendar, $other->membershipFor($otherOwner)]))
            ->assertNotFound();
    });

    test('a member can leave, and their events stay behind', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $event = Event::factory()->for($member)->onCalendar($calendar)->create();

        $this->actingAs($member)
            ->delete(route('memberships.leave', $calendar))
            ->assertRedirect(route('calendar'));

        expect($calendar->membershipFor($member))->toBeNull();
        expect($member->refresh()->current_calendar_id)->toBeNull();
        $this->assertModelExists($event);
    });

    test('the owner cannot leave their own calendar', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);

        $this->actingAs($owner)
            ->delete(route('memberships.leave', $calendar))
            ->assertForbidden();

        expect($calendar->membershipFor($owner))->not->toBeNull();
    });

    test('someone waiting for approval can withdraw their request', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $applicant = User::factory()->create();
        $calendar->members()->attach($applicant, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        $this->actingAs($applicant)
            ->delete(route('memberships.leave', $calendar))
            ->assertRedirect(route('calendar'));

        expect($calendar->membershipFor($applicant))->toBeNull();
    });

    test('a stranger cannot leave a calendar they were never on', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);

        $this->actingAs(User::factory()->create())
            ->delete(route('memberships.leave', $calendar))
            ->assertForbidden();
    });
});

describe('the join code', function () {
    test('the owner can rotate it, which invalidates the old one', function () {
        $owner = User::factory()->create();
        $calendar = calendarFor($owner);
        $oldCode = $calendar->join_code;

        $this->actingAs($owner)
            ->put(route('calendars.join-code.update', $calendar))
            ->assertRedirect();

        expect($calendar->refresh()->join_code)->not->toBe($oldCode);
        expect(Calendar::query()->where('join_code', $oldCode)->exists())->toBeFalse();
    });

    test('a plain member cannot rotate it', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $calendar = calendarFor($owner, $member);
        $oldCode = $calendar->join_code;

        $this->actingAs($member)
            ->put(route('calendars.join-code.update', $calendar))
            ->assertForbidden();

        expect($calendar->refresh()->join_code)->toBe($oldCode);
    });
});
