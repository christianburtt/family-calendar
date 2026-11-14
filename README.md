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

### Medical records

A separate section, switched on per calendar, holding scanned medical paperwork with its text searchable.

- **One folder per person** — a folder is a person, not an account, so a child without a login gets one just the same.
- **Scanned or photographed** — drop in the PDF a clinic emailed, or take a photo on a phone. HEIC from an iPhone is converted on arrival.
- **Searchable text** — OCR reads every page and search folds Romanian diacritics, so `reteta` finds `Rețetă` and `judetean` finds `Județean`.
- **Editable text** — when OCR gets a blurry page wrong, the text can be corrected by hand and search picks up the fix.
- **Suggested details** — a cheap model reads the text and proposes a title, type, date and clinic; nothing is saved until somebody accepts it.
- **Household-wide access** — every approved member of the calendar sees every folder. Deleting a document is limited to whoever uploaded it or the household's owner.
- **Soft deletes** — removed documents stay in a bin for 30 days before the file goes too.
- **Access log** — opening or downloading a document records who did it.

See [`MEDICAL_MODULE.md`](MEDICAL_MODULE.md) for the design and the reasoning behind it.


## Tech stack

| Layer    | Tools                                                                |
| -------- | -------------------------------------------------------------------- |
| Backend  | PHP 8.4, Laravel 13, Laravel Fortify, Laravel Wayfinder              |
| Frontend | Inertia v3, Vue 3, TypeScript, Tailwind CSS 4, Reka UI, Lucide icons |
| Build    | Vite (via `vite-plus`), Wayfinder Vite plugin                        |
| Data     | MySQL (SQLite supported), Eloquent                                   |
| Quality  | Pest, Pint, Larastan/PHPStan, `vue-tsc`                              |

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

| Name      | Email                |
| --------- | -------------------- |
| Test User | `test@example.com`   |
| Sam       | `sam@example.com`    |
| Jordan    | `jordan@example.com` |
| Riley     | `riley@example.com`  |

### Run the app

```bash
composer run dev
```

This starts the PHP server, the Vite dev server, the queue listener and log tailing (`php artisan dev`). Then open the URL shown by Artisan, usually <http://localhost:8000>.

Prefer separate terminals? Run `php artisan serve` and `npm run dev` yourself.

## Development commands

| Command                               | What it does                                               |
| ------------------------------------- | ---------------------------------------------------------- |
| `composer run dev`                    | Run server, Vite, queue and logs together                  |
| `npm run build`                       | Build production assets                                    |
| `npm run types:check`                 | Type-check the frontend with `vue-tsc`                     |
| `npm run check` / `npm run check:fix` | Lint/format frontend code                                  |
| `vendor/bin/pint --dirty`             | Format changed PHP files                                   |
| `composer types:check`                | Run PHPStan (Larastan)                                     |
| `php artisan test --compact`          | Run the test suite                                         |
| `composer ci:check`                   | Everything CI runs: frontend checks, type checks and tests |

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
  pages/                     Inertia pages (Welcome, Calendar, auth, settings, medical)
  components/calendar/       MonthView, WeekView, DayAgenda, EventDialog
  components/medical/        DocumentUploader
  components/ui/             Reka UI / shadcn-vue style components
  lib/                       Date helpers, colours, toast handling
routes/
  web.php  settings.php      App and settings routes
  medical.php                Medical records routes
tests/
  Feature/                   Auth, calendar, events, settings and medical tests
```

The medical module is namespaced under `app/Medical/` (models, controllers, jobs,
OCR and metadata drivers, policies, search), so the calendar never imports it and
the next module can be copied from the same shape.

## Medical records setup

The module works with no credentials: documents upload, file, preview, and accept
typed-in text. Reading them needs an OCR key.

```dotenv
OPPER_API_KEY=your-key
MEDICAL_OCR_DRIVER=opper          # "null" reads nothing; the app still works
MEDICAL_OCR_MODEL=mistral/mistral-ocr-latest
MEDICAL_METADATA_ENABLED=true
MEDICAL_METADATA_MODEL=deepseek-v4.1-flash
```

Check the model strings against your gateway before uploading anything:

```bash
php artisan medical:check-ocr
```

Then switch the module on for a household from **Calendar → People → Medical records**.

**Upload limits.** The default PHP ceiling is 20 MB, which a twelve-page clinic
PDF exceeds. Raise `upload_max_filesize` and `post_max_size` to at least 64M in
your php.ini and restart PHP. `MEDICAL_MAX_UPLOAD_MB` caps uploads in the
application itself.

**Storage.** Files live on the environment's default disk. Locally that is
`storage/app/medical` via the `medical` disk; on Laravel Cloud it is the private
bucket you attach, because Cloud injects `FILESYSTEM_DISK` and the credentials.
A document stores a null disk to mean "wherever the default is", so the same rows
work on both. To keep a self-hosted install on a named disk instead, set
`MEDICAL_DISK=medical`.

**Backups are not automated.** The bucket and the database both need backing up
together; a restore of one without the other leaves documents with no files.

## Deploying to Laravel Cloud

Three things need doing in the Cloud dashboard, and one in the repo.

**In the repo** (already done): `league/flysystem-aws-s3-v3` is required. Without
it, `Storage::disk()` throws `Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found`.

**1. Attach a private bucket as the default disk.** Add an Object Storage bucket,
set visibility to **private**, and tick it as the environment's default disk. Do
not create a bucket with a disk name matching `medical`: the app reads the default
disk, which is what lets the same code run locally and in production. Choose the
**European Union** jurisdiction so the scans stay in the EU.

**2. Add a managed queue.** Add compute → Managed queue, name it `default`, and
deploy. Cloud then sets `QUEUE_CONNECTION=cloud` and the OCR job is picked up
without any further configuration. Flex is enough: it scales to zero and wakes
when a scan is uploaded.

> A managed queue routes **every** job dispatched without an explicit connection.
> This app only dispatches `ProcessMedicalDocument`, so there is nothing else to
> reconcile. If a second queue driver is ever needed, dispatch that job with
> `->onConnection('database')` instead of changing `QUEUE_CONNECTION`.

**3. Set the environment variables.** Cloud injects the bucket and queue
credentials; the medical ones are yours:

```ini
APP_KEY=                     # paste from local: php artisan key:generate --show
OPPER_API_KEY=               # in Secrets, not as a plain variable
MEDICAL_OCR_DRIVER=opper
MEDICAL_OCR_MODEL=mistral/mistral-ocr-latest
MEDICAL_METADATA_ENABLED=true
MEDICAL_METADATA_MODEL=deepseek-v4.1-flash
MEDICAL_MAX_UPLOAD_MB=25
```

`OPPER_API_KEY` belongs in **Secrets**, so it is encrypted and can be attached to
staging and preview environments too without copying it around.

**Beware `APP_KEY`.** If it differs from the one that encrypted a `dosars.cnp`
value, the encrypted cast cannot decrypt it and reading a folder throws. Copy the
key from your local `.env` if you are migrating existing data, or accept that the
CNP column is empty on a fresh production database.

**Not needed:** `php artisan storage:link` (the bucket is not served from disk),
`queue:restart` and `horizon:terminate` (Cloud restarts workers), and
`optimize:clear`. Keep `php artisan migrate --force` as the deploy command and
`php artisan optimize` in the build command.

**Uploads.** Cloud's base image sets a generous PHP `upload_max_filesize`, so the
20 MB ceiling you hit on Herd does not apply. `MEDICAL_MAX_UPLOAD_MB` is the app's
own cap and is enforced in validation.

**HEIC.** Cloud's PHP image has no `imagick` extension by default, but it ships the
ImageMagick **command-line** tools (`magick`) plus libheif, which the normaliser
uses as its second choice after the extension. If you would rather use the
extension, add the **ImageMagick** build add-on (Private Cloud only). Either way a
10-bit iPhone photo converts.


## Notes

- Events have no recurrence support yet; each event is a single time range.
- Hidden-member preferences live in the browser (`localStorage`), not the database.
- `AGENTS.md` contains the project's conventions for AI-assisted development; `boost.json` configures Laravel Boost tooling.
- PDF thumbnails are not generated: rasterising a PDF needs Ghostscript, so PDFs open in the browser's own viewer instead.
- On a Linux host, HEIC conversion depends on Imagick's libheif build; the macOS `sips` fallback does not exist there.
