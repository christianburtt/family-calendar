<?php

use App\Models\Calendar;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the medical section is closed to guests', function () {
    $this->get(route('medical.index'))->assertRedirect(route('login'));
});

test('the medical section is closed when the household has not enabled it', function () {
    $owner = User::factory()->create();
    $calendar = calendarFor($owner);
    $calendar->setModule(Calendar::MODULE_MEDICAL, false);
    $owner->switchTo($calendar);

    $this->actingAs($owner)
        ->get(route('medical.index'))
        ->assertForbidden();
});

test('a household member with the module on can open the medical section', function () {
    [$calendar, $owner] = medicalHousehold();
    $member = User::factory()->create(['name' => 'Elena']);
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $this->actingAs($member)
        ->get(route('medical.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('medical/Index'));
});

test('someone from another household cannot open a folder they were not given', function () {
    [$firstCalendar] = medicalHousehold();
    [$secondCalendar, $outsider] = medicalHousehold();

    $dosar = dosarIn($firstCalendar, 'Maria');

    $this->actingAs($outsider)
        ->get(route('medical.dosars.show', $dosar))
        ->assertForbidden();
});

test('a folder from another household is not listed', function () {
    [$firstCalendar, $firstOwner] = medicalHousehold();
    dosarIn($firstCalendar, 'Maria');

    [, $secondOwner] = medicalHousehold();

    $this->actingAs($secondOwner)
        ->get(route('medical.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('dosars', 0));
});

test('enabling the module is the owner\'s decision', function () {
    $owner = User::factory()->create();
    $calendar = calendarFor($owner);
    $calendar->setModule(Calendar::MODULE_MEDICAL, false);

    $member = User::factory()->create();
    $calendar->members()->attach($member, ['role' => 'member', 'status' => 'active']);

    $this->actingAs($member)
        ->patch(route('calendars.modules.update', $calendar), ['enabled' => true])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('calendars.modules.update', $calendar), ['enabled' => true])
        ->assertRedirect();

    expect($calendar->fresh()->hasModule(Calendar::MODULE_MEDICAL))->toBeTrue();
});
