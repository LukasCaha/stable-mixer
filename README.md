# Stable Mixer

Laravel admin and companion API for a horse-stable voice-memo product. A stable is a tenant. The native app uploads audio with that stable’s public tenant code. This app stores the file, queues speech-to-text, and lets stable mates review transcripts.

The companion app is not authenticated. Anyone who knows an active stable’s pairing code can upload. User auth on the native app comes later. The Filament admin is a normal login, and each account belongs to exactly one stable.

## Stack

- Laravel 12
- Filament 4, with each Filament team set to one `Stable`
- Database queue (Redis works if you change `QUEUE_CONNECTION`)
- Private `memos` disk (local by default, S3 if configured)
- OpenAI-compatible Whisper endpoint (`STT_API_KEY`, `STT_BASE_URL`)

## Requirements

- PHP 8.2+ with `intl`, `pdo_sqlite`, and `sqlite3` (or another database you configure)
- Composer

If `pdo_sqlite` and `intl` are installed but commented out in `php.ini`, `composer dev` turns them on for the dev server and queue worker. `php artisan serve` does the same for the web process.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Demo login (also a super admin, so the all-stables list is visible):

| | |
| --- | --- |
| Admin | http://localhost:8000/admin |
| Email | `owner@stable-mixer.test` |
| Password | `password` |
| Stable | Demo Stable |
| Tenant code | `A1B2C3D4` |

Run the dev server, queue worker, and Vite together:

```bash
composer dev
```

`QUEUE_CONNECTION=database` is the default. Uploads stay `queued` until a worker runs `TranscribeMemo`.

Set the speech-to-text provider in `.env`:

```bash
STT_API_KEY=sk-...
STT_BASE_URL=https://api.openai.com/v1
STT_MODEL=whisper-1
```

`STT_BASE_URL` is any OpenAI-compatible root. The job POSTs `{STT_BASE_URL}/audio/transcriptions`.

## Companion upload

`POST /api/v1/memos` accepts multipart form data:

- `file` — audio (`m4a`, `mp3`, `wav`, `aac`, `mp4`, `webm`, `caf`, `ogg`), max 20 MB
- tenant code — header `X-Tenant: {code}` or form field `tenant`
- `recorded_at` — optional date

The code must match an active stable. Response:

```json
{ "id": "uuid", "status": "queued" }
```

Header example:

```bash
curl -sS -X POST http://localhost:8000/api/v1/memos \
  -H "Accept: application/json" \
  -H "X-Tenant: A1B2C3D4" \
  -F "file=@memo.m4a;type=audio/mp4" \
  -F "recorded_at=2026-09-30T12:00:00Z"
```

Form field example:

```bash
curl -sS -X POST http://localhost:8000/api/v1/memos \
  -H "Accept: application/json" \
  -F "tenant=A1B2C3D4" \
  -F "file=@memo.m4a;type=audio/mp4"
```

Health check: `GET /api/v1/health` → `{"status":"ok"}`.

The phone confirms a code before it connects:

`GET /api/v1/stables/{code}`

An active stable returns `{ "name": "North Barn", "tenant_code": "A1B2C3D4" }`. Unknown and inactive codes both return `404` with `{ "message": "Stable not found." }`. Lookups are rate-limited per IP (`MEMO_RATE_LIMIT`, default 30 per minute).

Uploads are rate-limited per tenant code (`MEMO_RATE_LIMIT`, default 30 per minute). Unknown and inactive codes are rejected and still count toward the limit for that code.

Memos move through `queued` → `processing` → `done`, or `failed` with `error` set. `TranscribeMemo` implements `App\Contracts\Failable`; its `failed()` hook writes the error onto the memo.

Raise `upload_max_filesize` and `post_max_size` in PHP if you accept longer recordings than the default 2 MB.

## Create a stable

Open `/admin/register`, or use the sign-up link on the login page. The form asks for a name, email, password, and stable name. It creates that person as the owner of a new stable and signs them in.

The dashboard shows the pairing code and its QR. Share that with the phone. Owners can still invite mates from Stable mates. Creating a stable from the Stables list stays off; that list is for super admins.

## Admin

Login is scoped to the user’s single stable. The panel URL is `/admin/{tenant_code}`.

- **Dashboard** — memos today, pending transcription, failed, and the latest memos
- **Memos** — filter by status, open a memo to play or download the audio and read the transcript
- **Stable mates** — list users in this stable. Owners invite mates with a name, email, role, and password (no invitation email in v0)
- **Stable settings** (tenant menu) — edit the name and whether uploads are accepted. The tenant code is read-only after create. **Regenerate tenant code** asks for confirmation, then the old code stops working
- **Stables** — super admins (`users.is_super_admin`) can list and edit every stable. Everyone else only sees their own team. Super admin does not make an account a member of other stables

To turn off the demo super-admin flag:

```bash
php artisan tinker
```

```php
\App\Models\User::query()->where('email', 'owner@stable-mixer.test')->update(['is_super_admin' => false]);
```

## Storage

New audio is written to the `memos` disk (`storage/app/private/memos` when `MEMO_DISK_DRIVER=local`). The row stores `disk` and `disk_path` so playback still works if you later change the default disk.

S3:

```bash
composer require league/flysystem-aws-s3-v3
```

```bash
MEMO_DISK_DRIVER=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

## Tests

```bash
php artisan test
```

## Out of scope for v0

Billing, on-device speech-to-text, users in more than one stable, analytics, and a mobile UI.
