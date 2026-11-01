<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarJoinCodeController;
use App\Http\Controllers\CalendarJoinController;
use App\Http\Controllers\CalendarMembersController;
use App\Http\Controllers\CalendarsController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/calendar')->name('dashboard');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::resource('events', EventController::class)->only(['store', 'update', 'destroy']);

    Route::get('calendars/create', [CalendarsController::class, 'create'])->name('calendars.create');
    Route::post('calendars', [CalendarsController::class, 'store'])->name('calendars.store');
    Route::patch('calendars/{calendar}', [CalendarsController::class, 'update'])->name('calendars.update');
    Route::put('calendars/{calendar}/current', [CalendarsController::class, 'select'])->name('calendars.select');

    Route::get('calendars/{calendar}/members', [CalendarMembersController::class, 'index'])
        ->name('calendars.members');

    Route::patch('calendars/{calendar}/members/{membership}', [CalendarMembersController::class, 'approve'])
        ->scopeBindings()
        ->name('memberships.approve');

    Route::delete('calendars/{calendar}/members/{membership}', [CalendarMembersController::class, 'destroy'])
        ->scopeBindings()
        ->name('memberships.destroy');

    Route::delete('calendars/{calendar}/membership', [CalendarMembersController::class, 'leave'])
        ->name('memberships.leave');

    Route::put('calendars/{calendar}/join-code', [CalendarJoinCodeController::class, 'update'])
        ->name('calendars.join-code.update');

    // Joining is two steps so link previews and mail scanners cannot add anyone.
    Route::get('join/{code}', [CalendarJoinController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('join.show');

    Route::post('join/{code}', [CalendarJoinController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('join.store');
});

require __DIR__.'/settings.php';
