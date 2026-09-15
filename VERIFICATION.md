# BilbyDugout — Verification runbook

This stack was scaffolded in an environment **without PHP, Composer, MariaDB,
Docker, or npm**, so it has not been run there. A full static verification
passed (167 checks: JSON, JS/Vue syntax, PSR-4, route→controller, migrations,
imports). Use the steps below to do the real runtime pass on a provisioned
machine.

## Prerequisites
- PHP 8.2+ with `pdo_mysql`, Composer
- MariaDB 11.x
- Node 18+ / npm
- (Azure AD app registration only needed for the *real* login; local smoke
  testing uses the dev-login bypass below — no Azure required.)

## 1. Database
```bash
mysql -u root -p -e "CREATE DATABASE bilbydugout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bilby'@'%' IDENTIFIED BY 'secret';
GRANT ALL ON bilbydugout.* TO 'bilby'@'%'; FLUSH PRIVILEGES;"
```

## 2. Backend: install, migrate, seed, test
```bash
cd backend
cp .env.example .env            # set DB_*, JWT_SECRET; keep APP_ENV=local
                                # DB_CONNECTION=mysql — a running MySQL/MariaDB is required
composer install
composer migrate                # phinx migrate  → creates all tables
vendor/bin/phinx seed:run -s DevSeed -c phinx.php   # sample data (4 roles)
composer test                   # phpunit (policy + guard unit tests)
composer start                  # php -S localhost:8080 -t public
```
Expected: migrate reports 32 migrations; `phpunit` green; server on :8080.

## 3. API smoke test (no Azure — uses /auth/dev-login, APP_ENV=local)
```bash
bash scripts/smoke.sh           # from repo root, with the API running
```
It signs in as each seeded role and exercises the scoped endpoints, asserting
tenant isolation (e.g. a client cannot see another tenant's data).

## 4. Frontend
```bash
cd frontend
cp .env.example .env.local      # VITE_API_BASE=http://localhost:8080/api/v1 ; VITE_DEV_LOGIN=true
npm install
npm run dev                     # http://localhost:5173
```
Ensure the backend `.env` `CORS_ALLOWED_ORIGINS` includes `http://localhost:5173`.

## 5. SPA smoke test (manual)
On the login screen, use the **Dev sign-in** buttons (shown when
`VITE_DEV_LOGIN=true`) to enter as each role and verify:

| Role (button) | Expect |
|---|---|
| **Global Admin** | Agency overview dashboard; Admin tab visible (Team/Subscriptions/Invoices populated); all 5 seeded requests; Calendar shows scheduled items; Analytics charts. |
| **Agency Staff** | "My work" = only requests assigned to the designer (1, 2, 4); no Admin tab; can post internal notes on a request. |
| **Client Owner** | Acme dashboard; sees Acme's requests only; on a `review` request (Brand Refresh) can Approve / Request revision with a signature; New Request wizard works. |
| **Client Member** | Acme dashboard; can create a request; cannot approve; never sees other tenants. |

Cross-tenant check: as a client, open devtools and call
`fetch('/api/v1/subscriptions')` — you should get only Acme's row (or none),
never another client's.

## Notes
- `/auth/dev-login` and the SPA "Dev sign-in" panel are **hard-gated to
  `APP_ENV=local` / `VITE_DEV_LOGIN=true`** — they no-op (404 / hidden) otherwise.
  Remove or keep disabled in any shared/staging/production build.
- Phase 3 (Stripe, MS Graph, Anthropic Claude LLM, cron) is not wired yet; those
  endpoints/features are absent by design.
