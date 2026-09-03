# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Rebit — a deployment of **ColibriPlus**, a commercially licensed Laravel 12 / PHP 8.2 social network script (Vue 3 SPA + Blade MPA). Upstream is currently **2.1.0 (10 May 2026)**.

Because upstream is third-party paid code, this repo uses a **vendor-branch model**. Read the branch rules below before any git operation — violating them causes real damage that is tedious to undo.

## Branch model

| Branch | Contents | Rule |
|---|---|---|
| `main` | Frozen snapshot of the production tree after the April 2026 malware cleanup | Never modify, never commit to, never push |
| `terla` | Pristine upstream ColibriPlus 2.1.0 | **Never put project code, tooling, or config here** |
| `dev` | Upstream + project customizations. GitHub default branch. | All work happens here |

`git diff terla dev` must only ever show project customizations (currently 57 files). If it starts showing upstream churn, `terla` has been polluted.

`terla` deliberately branches from `main` rather than being an orphan — the shared ancestor is what makes `git merge terla` a real 3-way merge instead of thousands of add/add conflicts. Do not "fix" this.

For upstream imports, merges, rename-trap detection, and push conventions, **use the `github-workflow` skill** (`.claude/skills/github-workflow/`). It encodes hard-won specifics — e.g. build from a vendor drop's `Source/` not `Updates/`, and strip `storage/debugbar` + `.DS_Store` contamination before committing, because upstream's `.gitignore` differs from this project's.

For server setup / deployment questions, use the `colibriplus-installation` skill.

## Commands

`vendor/` and `node_modules/` are **not installed** in this checkout. Install before anything that needs them:

```bash
composer install
npm install
```

**Tests** (PHPUnit; suites `Unit` and `Feature`, only example tests exist so far):

```bash
php artisan test                        # all
php artisan test --filter=ExampleTest   # single test / class
php artisan test tests/Feature          # one suite
./vendor/bin/phpunit --filter=methodName
```

`phpunit.xml` forces `APP_ENV=testing`, `DB_DATABASE=testing`, array cache/session/mail, and `QUEUE_CONNECTION=sync`.

**Frontend build — the important gotcha:**

```bash
npm run dev      # vite dev server
npm run build    # vite build + ALL five dark-theme Tailwind passes
npm run build:vite   # vite ONLY — leaves dark-theme CSS stale
```

Dark themes are **not** part of the Vite pipeline. They are separate `@tailwindcss/cli` invocations writing straight into `public/build/assets/*-dark.css` (desktop, mobile, auth, business, admin). Blade layouts load them conditionally via `theme_name() == 'dark'`. Running only `vite build` silently ships stale dark CSS. Use `npm run build`, or `npm run build:dark` to refresh just those.

**Lint / static analysis:** no linter, formatter, or static analyser is configured. `php -l` is the only syntax check available.

**Deploy:** `./deploy.sh` (project-specific, not upstream) — `git pull --rebase`, `artisan down`, `composer install --no-dev`, `migrate --force`, config/route/view cache, `artisan up`.

## Architecture

### Routing is centralised, not auto-discovered

`bootstrap/app.php` registers **every** route file explicitly inside the `withRouting(using: ...)` closure, each with its own middleware stack. Adding a route file does nothing until you register it there. Route groups:

- `routes/web.php`, `routes/api.php` (prefix `api`), plus `document`, `social`, `downloads`
- `routes/admin/web.php` — prefixed by `config('app.admin_prefix')`, `admin-area` group (`web` + `admin`)
- `routes/business.php` — prefix `business`, requires `auth` + `user.status`
- `routes/webhooks/payment_webhooks.php` and `routes/callbacks.php` — `withoutMiddleware()`, so signature verification inside the controllers is the only protection

Middleware aliases are also declared there: `user.status`, `device.identifier`, `terminator`, `restrict.ip`, `features.status`, `sided.layout`, `api.key`, `admin`, `log.request`.

### Four frontends in one app

`vite.config.js` has separate entry points per surface:

- **SPA (Vue 3)** — `resources/js/spa/apps/desktop/` and `.../mobile/`, two independent apps with their own routers and component trees. Served by Blade shells in `resources/views/layouts/spa/`.
- **MPA (Blade + Livewire + Alpine)** — admin panel, business panel, document pages. Views under `resources/views/apps/mpa/`, ~35 Livewire components in `app/Livewire/`.

When changing shared Blade components in `resources/views/components/`, remember they are consumed by both admin and business panels.

### Non-standard directories

- **`var/`** — editable runtime data outside Laravel's `config/`: `var/config/` (assets, filesystems, user), `var/world/` (countries, report reasons, user fields, per-locale PHP arrays), `var/providers/index.php` (extra service providers). Accessed via the `var_path()` helper, not `config()`. Upstream 2.1.0 moved several settings out of `config/*.php` into the **database** with admin editors (ffmpeg, mail, social, payment, wallet, pwa, app) — check the DB before assuming a config file is authoritative.
- **`plugins/`** — PSR-4 `Plugins\` namespace, but **gitignored**. Providers listed in `var/providers/index.php`. Registering a provider for a plugin that isn't installed is a boot-time fatal.

### Conventions worth knowing

- `app/Support/helpers.php` is globally autoloaded via composer `files`. Provides `me()` (current user — one of the most-referenced symbols in the codebase), `theme_name()`, `var_path()`, `storage_url()`, `encode_id()`/`decode_id()` (Hashids), `route_is()`, and per-domain loggers (`payment_log()`, `chat_log()`, `story_log()`, …).
- Table names come from `App\Database\Configs\Table` constants, not string literals.
- `app/` is layered well beyond default Laravel: `Actions/`, `Services/`, `Enums/`, `Data/`, `Settings/`, `Policies/`, `Rules/`, `MediaApi/`, `Telegram/`, `Ai/`.
- Real-time is Laravel Reverb; queues are Horizon. Both need supervisor processes in production — the app degrades quietly without them.

## Code graph

A Graphify index is built at `graphify-out/graph.json` (gitignored). Prefer it over repo-wide grep when tracing relationships:

```bash
graphify explain "FeedController"
graphify affected "SomeSymbol" --depth 2
graphify query "how are posts ranked"
```

Rebuild after significant changes: `graphify update . --no-cluster`.

Known blind spot: it misses string-registered wiring. Middleware aliased in `bootstrap/app.php` (e.g. `VerifyApiKey`) returns no callers — an empty `affected` result there is not proof of dead code.

## Gotchas

- **`.claude/` is gitignored by upstream's `.gitignore`** (line 13). Skills and settings in this repo are local-only and will not reach collaborators unless force-added.
- **Blade balance checks produce false positives.** `@unless` and `@error` both close with `@endif` in upstream code. Confirm a flagged file actually differs from the upstream tree before treating it as broken.
- **`.env.example` is tracked** and currently ships upstream's vendor-default `REVERB_*` and `GIPHY_API_KEY` values — these are live in production and identical for every ColibriPlus licensee.
- `config/cors.php` reads `CORS_ALLOWED_ORIGINS` (project change; upstream had `['*']` with credentials enabled).
- `FeedController` uses the project's `FeedRankingService` for ordering instead of upstream's `orderBy` chain. Do not reintroduce upstream's chain alongside it — its `created_at DESC` would dominate and silently kill the custom ranking.
