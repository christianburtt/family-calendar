# Family Calendar

A shared family calendar for the whole household. Every family member gets their own colour and timezone, and everyone sees one combined calendar — month or week view — on any device.

Built on the Laravel Vue starter kit with [Laravel 13](https://laravel.com), [Inertia v3](https://inertiajs.com), Vue 3 and Tailwind CSS 4.

## Features

- **One shared calendar** — every member's events appear together, colour-coded per person.
- **Month and week views** — switch views, jump to today, or navigate by month/week. Week view includes a live "now" indicator and a mobile-friendly day agenda.
- **Per-member colours** — each person picks their calendar colour; taken colours are shown so everyone stays distinct.
- **Timezone aware** — events are stored in UTC and rendered in the viewer's timezone, no matter whose event it is.
- **Owner-only editing** — anyone in the family can see all events, but only the event's owner can edit or delete it.
- **Filter by member** — toggle family members on/off; the choice is remembered in the browser.
- **Stays fresh** — the calendar polls for changes every 60 seconds, so a fridge-mounted tablet stays up to date.
- **Accounts with modern auth** — registration, email verification, password reset, two-factor authentication and passkeys via Laravel Fortify.

## Tech stack

| Layer | Tools |
| --- | --- |
| Backend | PHP 8.4, Laravel 13, Laravel Fortify, Laravel Wayfinder |
| Frontend | Inertia v3, Vue 3, TypeScript, Tailwind CSS 4, Reka UI, Lucide icons |
| Build | Vite (via `vite-plus`), Wayfinder Vite plugin |
| Data | MySQL (SQLite supported), Eloquent |
| Quality | Pest, Pint, Larastan/PHPStan, `vue-tsc` |

## Requirements

- PHP 8.4+ with the usual Laravel extensions
- [Composer](https://getcomposer.org) 2
- Node.js 22+ and npm
- MySQL 8 (or SQLite if you prefer — see below)

## Getting started

```bash
git clone <repository-url> family-calendar
cd family-calendar

# Installs PHP and JS dependencies, creates .env, generates a key, migrates and builds assets
composer setup
```

### Database

`.env.example` points at MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=family_calendar
DB_USERNAME=root
DB_PASSWORD=
```

Create the `family_calendar` database, or switch to SQLite instead:

```dotenv
DB_CONNECTION=sqlite
```

```bash
touch database/database.sqlite
php artisan migrate
```

### Demo data (optional)

Seed a demo family of four with 40 events:

```bash
php artisan db:seed
```

All seeded accounts use the password `password`:

| Name | Email |
| --- | --- |
| Test User | `test@example.com` |
| Sam | `sam@example.com` |
| Jordan | `jordan@example.com` |
| Riley | `riley@example.com` |

### Run the app

```bash
composer run dev
```

This starts the PHP server, the Vite dev server, the queue listener and log tailing (`php artisan dev`). Then open the URL shown by Artisan, usually <http://localhost:8000>.

Prefer separate terminals? Run `php artisan serve` and `npm run dev` yourself.

## Development commands

| Command | What it does |
| --- | --- |
| `composer run dev` | Run server, Vite, queue and logs together |
| `npm run build` | Build production assets |
| `npm run types:check` | Type-check the frontend with `vue-tsc` |
| `npm run check` / `npm run check:fix` | Lint/format frontend code |
| `vendor/bin/pint --dirty` | Format changed PHP files |
| `composer types:check` | Run PHPStan (Larastan) |
| `php artisan test --compact` | Run the test suite |
| `composer ci:check` | Everything CI runs: frontend checks, type checks and tests |

## Testing

Tests use Pest with an in-memory SQLite database (see `phpunit.xml`), so no setup is needed:

```bash
php artisan test --compact

# Narrower runs
php artisan test --compact tests/Feature/CalendarTest.php
php artisan test --compact --filter="month view"
```

Coverage includes authentication, profile/security settings, calendar rendering (month/week ranges and timezone conversion) and event creation, editing and deletion permissions.

## How it works

- `GET /calendar` (`CalendarController`) resolves the requested `view` (`month` or `week`) and `date`, expands it to a date range in the viewer's timezone, loads overlapping events and renders the Inertia `Calendar` page.
- Events are created, updated and deleted through `EventController`. Form requests convert the wall-clock times submitted by the browser from the user's timezone to UTC, and `EventPolicy` restricts writes to the event's owner.
- `EventResource` converts stored UTC times back into the viewer's timezone as wall-clock strings (`Y-m-d\TH:i`), so the frontend never does timezone maths.
- Shared family members and their colours come from the `users` table; the current user's colour and timezone are editable under **Settings → Profile**.

## Project structure

```
app/
  Http/Controllers/          Calendar, event and settings controllers
  Http/Requests/             Event and settings validation
  Http/Resources/            Event API resource (timezone conversion)
  Models/                    User and Event
  Policies/                  Event ownership rules
  Providers/                 App defaults and Fortify configuration
database/
  factories/ seeders/        Demo family and events
  migrations/                Users, events, passkeys, 2FA, queues/cache
resources/js/
  pages/                     Inertia pages (Welcome, Calendar, auth, settings)
  components/calendar/       MonthView, WeekView, DayAgenda, EventDialog
  components/ui/             Reka UI / shadcn-vue style components
  lib/                       Date helpers, colours, toast handling
routes/
  web.php  settings.php      App and settings routes
tests/
  Feature/                   Auth, calendar, events and settings tests
```

## Notes

- Events have no recurrence support yet; each event is a single time range.
- Hidden-member preferences live in the browser (`localStorage`), not the database.
- `AGENTS.md` contains the project's conventions for AI-assisted development; `boost.json` configures Laravel Boost tooling.
