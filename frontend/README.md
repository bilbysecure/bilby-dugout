# BilbyDugout Web (Vue 3)

Phase 4 skeleton — the **single unified portal** with role-based dashboards
(architecture §10). One login, one shell; the dashboard and navigation are
chosen from the authenticated user's role.

## Stack
- Vue 3 (Composition API) + Vite
- Pinia (auth/session state)
- Vue Router (one route table + a single role-aware guard)
- @tanstack/vue-query (server state)
- axios (JWT bearer + silent refresh)

## What's implemented
- **Login** (`views/Login.vue`) → `POST /auth/login` (the backend-driven ROPC flow). Access token kept in memory; refresh via the httpOnly cookie at startup and on 401.
- **AppShell** (`components/AppShell.vue`) — unified nav + sign-out, role badge.
- **DashboardRouter** (`views/DashboardRouter.vue`) picks the dashboard by role:
  - `global_admin` → agency overview
  - `agency_staff` → "my work" (assigned; managers see all)
  - `client_owner` / `client_member` → company dashboard
- **Requests** list + **RequestDetail** (comments, and owner approve / request-revision — exercises the Phase 2 API).
- **New Request** intake wizard (`views/NewRequest.vue`) → `POST /requests`. Role-aware: agency users get a client step; the nav item only shows for managers/clients (matches `RequestPolicy::create`).
- **Notification bell** in the shell (`components/NotificationBell.vue`) with unread count, polling every 60s and sharing the `['notifications']` query cache.
- **Brand Hub** and **Notifications** (mark read / read-all).
- **Content calendar** (`views/ContentCalendar.vue`) — month grid from requests' publish/due dates.
- **Analytics** (`views/Analytics.vue`) — status/type/priority distributions + completion rate, computed client-side from the scoped requests (CSS `BarChart`, no chart lib).
- **Admin** (`views/Admin.vue`) — Team / Subscriptions / Invoices tabs (agency-only), read from the new admin endpoints.
- **Publishing** (`views/Publishing.vue` + `components/publishing/PostComposer.vue`) — modern social planning calendar across brands & channels: multi-channel composer, best-time suggestions, content approval (submit/approve/reject), scheduling & publish-now, tags (with rule automation), first comment, and Instagram product tagging. **Publishing settings** (`views/PublishingSettings.vue`) manages channels, tag-automation rules, RSS sources (with "Ingest now"), and the product catalog.
- API layer (`api/client.js`, `api/endpoints.js`) and query composables (`composables/queries.js`).

## Run

```bash
cp .env.example .env.local      # set VITE_API_BASE to your API (default :8080/api/v1)
npm install
npm run dev                     # http://localhost:5173
```

Requires the SlimPHP API running (see `../backend`). Ensure the API's
`CORS_ALLOWED_ORIGINS` includes `http://localhost:5173`.

> Scaffolded without a Node runtime available here — not yet `npm install`-ed or run.
> Install and run to verify.

## Not yet built (later)
- Admin create/edit (team members, subscriptions) + Stripe billing portal — Phase 3.
- Client picker in the intake wizard (depends on the subscriptions API — Phase 3); for now agency users type the client email.
- jQuery-wrapped widgets (rich-text editor, file-annotation viewer) — decision #7, added only where needed.
