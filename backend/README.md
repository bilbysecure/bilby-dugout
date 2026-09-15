# BilbyDugout API (SlimPHP)

Phase 0 + Phase 1 skeleton for the re-platformed BilbyDugout (BilbyPixel Request Portal).
See `../BilbyDugout-Architecture.md` for the full design.

## What's implemented

**Phase 0 — Foundations**
- Slim 4 app + PHP-DI container (`src/Application/Bootstrap.php`, `public/index.php`)
- Config from env (`config/settings.php`, `.env.example`)
- CORS + JSON error handling middleware
- Eloquent (illuminate/database) wired via Capsule
- Full MariaDB schema as Phinx migrations (`database/migrations/`) — all entities
- **Auth: Login form → Axios → Slim → Azure AD (ROPC) → app JWT** (`src/Auth/*`, `AuthController`)
  - `POST /api/v1/auth/login | refresh | logout`, `GET /api/v1/auth/me`
  - refresh token in httpOnly cookie; access JWT (firebase/php-jwt) as Bearer

**Phase 1 — Core domain + authorization**
- Eloquent models for every table (`src/Domain/Models/`)
- `Principal` + role model (`global_admin | agency_staff | client_owner | client_member`)
- **Tenant isolation**: `RequestRepository::visibleTo()` (query scoping) + `RequestPolicy` (per-action)
- `RequestService` ports Base44 `createRequest` / list / get semantics
- `GET/POST /api/v1/requests`, `GET /api/v1/requests/{id}`
- Policy unit tests (`tests/RequestPolicyTest.php`)

**Phase 2 — Workflow & supporting features**
- Sub-resource services + controllers, all scoped via `RequestGuard` (re-uses request visibility):
  - **Comments** — `GET/POST /requests/{id}/comments` (internal notes hidden from clients; notifies the other side)
  - **Approvals** — `GET/POST /requests/{id}/approvals` (client-owner sign-off; transitions status; notifies team)
  - **Revisions** — `GET/POST /requests/{id}/revisions`, `PATCH /revisions/{revisionId}`
  - **Reviews** — `GET/POST /requests/{id}/reviews` (client satisfaction 1–5)
  - **Activity** — `GET /requests/{id}/activity` (audit trail)
  - **Time** — `GET/POST /requests/{id}/time` (agency-only)
- **Notifications** — `GET /notifications`, `PATCH /notifications/{id}/read`, `POST /notifications/read-all` (recipient-scoped)
- **Brand** — `GET/POST /brand-kits`, `GET/PATCH /brand-kits/{id}`, `GET/POST /brand-kits/{id}/assets` (tenant-scoped via `BrandKitRepository`)
- **Admin reads** — `GET /team-members` (agency-only), `GET /subscriptions`, `GET /invoices` (agency=all / client=own; `stripe_customer_id` hidden). Writes + Stripe → Phase 3.
- `NotificationService` + `ActivityLogger` shared across services
- Guard unit tests (`tests/RequestGuardTest.php`)

**Social planning & publishing** (`src/Services/Publishing/`)
- **Channels** `/channels`, **Products** `/products` (IG tagging catalog), **Tag rules** `/tag-rules`, **RSS sources** `/rss-sources` (+ `/{id}/ingest`), **Posts** `/posts` (+ `/best-times`, `/{id}/submit|decision|schedule|publish|cancel`).
- Multi-brand/multi-channel targets (`scheduled_post_targets`), content-approval gate, **BestTimeService** (algorithmic), **TagRuleEngine** (content-tag automation), **RssService** (real public-feed ingestion → posts), and a **`PublishProvider`** interface with `StubPublishProvider` (real Meta/LinkedIn/etc. + IG product-tag submission land in Phase 3).
- All tenant-scoped via `Support\TenantScope`.

## Run locally

```bash
cp .env.example .env          # fill AZURE_*, JWT_SECRET, DB_*
composer install
composer migrate              # phinx migrate
composer start                # php -S localhost:8080 -t public
composer test                 # phpunit
```

Or `docker compose up` from the repo root (MariaDB + PHP dev server).

> No PHP runtime was available where this was scaffolded, so it has not been run here.
> Run `composer install && composer migrate && composer test` to verify in your environment.

## Next (not yet built)
- Phase 3: Stripe (portal + webhooks), MS Graph (email/Teams), Anthropic Claude LLM service (drafting / recommendations / brand-asset review), cron commands (`quota:check`, `approvals:remind`).
- Phase 4: Vue 3 SPA (unified portal, role-based dashboards).
