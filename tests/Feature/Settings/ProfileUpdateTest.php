<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'color' => '#7c3aed',
            'timezone' => 'Australia/Sydney',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->color)->toBe('#7c3aed');
    expect($user->timezone)->toBe('Australia/Sydney');
    expect($user->email_verified_at)->toBeNull();
});

test('legacy timezone names reported by some browsers are accepted', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'color' => $user->color,
            'timezone' => 'Asia/Calcutta',
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->timezone)->toBe('Asia/Calcutta');
});

test('timezone validation does not depend on the PHP build\'s backward links', function (string $timezone) {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'color' => $user->color,
            'timezone' => $timezone,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->timezone)->toBe($timezone);
})->with([
    'Asia/Calcutta',
    'Asia/Katmandu',
    'US/Eastern',
    'America/New_York',
    'Europe/Bucharest',
    'UTC',
]);

test('offsets and bare abbreviations are still rejected as timezones', function (string $timezone) {
    $user = User::factory()->create(['timezone' => 'UTC']);

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'color' => $user->color,
            'timezone' => $timezone,
        ])
        ->assertSessionHasErrors(['timezone' => 'The timezone field must be a valid timezone.']);

    expect($user->refresh()->timezone)->toBe('UTC');
})->with([
    '+02:00',
    '-05:00',
    'Z',
    'est',
    'utc',
    'Not/AZone',
]);

test('invalid profile colors and timezones are rejected', function (array $input, string $field, string $message) {
    $user = User::factory()->create(['color' => '#2563eb', 'timezone' => 'UTC']);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'color' => '#16a34a',
            'timezone' => 'Europe/London',
            ...$input,
        ]);

    $response
        ->assertSessionHasErrors([$field => $message])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->only('color', 'timezone'))
        ->toBe(['color' => '#2563eb', 'timezone' => 'UTC']);
})->with([
    'a color name instead of hex' => [['color' => 'blue'], 'color', 'The color field must be a valid hexadecimal color.'],
    'a shorthand hex color' => [['color' => '#fff'], 'color', 'The color field must be 7 characters.'],
    'an unknown timezone' => [['timezone' => 'Mars/Olympus_Mons'], 'timezone', 'The timezone field must be a valid timezone.'],
]);

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'color' => $user->color,
            'timezone' => $user->timezone,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
