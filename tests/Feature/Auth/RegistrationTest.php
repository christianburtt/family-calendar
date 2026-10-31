<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'color' => '#db2777',
        'timezone' => 'America/Chicago',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('calendar', absolute: false));

    $this->assertDatabaseHas(User::class, [
        'email' => 'test@example.com',
        'color' => '#db2777',
        'timezone' => 'America/Chicago',
    ]);
});

test('registration screen lists the colors already taken in the family', function () {
    User::factory()->create(['color' => '#16a34a']);

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/Register')
        ->where('takenColors', ['#16a34a'])
    );
});
