# AGENTS.md

Architecture and conventions for this codebase. Future agents (Claude, Copilot, Cursor, anything else that reads `AGENTS.md`) should follow these. Where this conflicts with auto-generated tool guidance, this file wins.

## Stack

- PHP 8.3, Laravel 11 (streamlined `bootstrap/app.php` structure — see below)
- Inertia v1 + Vue 3 + Tailwind 3
- MySQL via Laravel Sail; PHPUnit 11; Laravel Pint; Laravel Sanctum (API auth)

## Laravel 11 file structure

This project **does** use the Laravel 11 streamlined structure. Some auto-generated guidance may say otherwise — ignore it.

- `bootstrap/app.php` is the source of truth for middleware, routing config, and exception rendering (`withExceptions()`).
- There is no `app/Http/Kernel.php`.
- There is no `app/Exceptions/Handler.php`.
- API exception rendering lives in `bootstrap/app.php` and uses `App\Http\ApiResponse::error(...)`.

## Backend layers

```
app/
├── Actions/<Resource>/<Verb><Resource>Action.php
├── Enums/                          (TaskPriority, TaskStatus)
├── Http/
│   ├── ApiResponse.php             (success/error envelope helper)
│   ├── Controllers/                (thin web controllers)
│   ├── Controllers/Api/V1/         (thin API controllers)
│   ├── Middleware/HandleInertiaRequests.php
│   ├── Requests/<Resource>/        (FormRequests, one per write endpoint)
│   ├── Requests/Concerns/          (FormRequest traits)
│   └── Resources/                  (proper JsonResources, no envelope)
├── Models/
├── Observers/UserObserver.php      (seeds default board on signup)
├── Policies/                       (BoardPolicy, TaskPolicy)
├── Providers/AppServiceProvider.php
├── Services/<Domain>/<Domain>Service.php
└── Support/
    ├── BoardTaskAssignments.php    (pivot query helpers)
    └── Presenters/                 (response shape helpers for Inertia)
```

### Conventions

- **Actions** — one Action = one verb. Single `execute(...)` method. Constructor-inject other Actions/services. `DB::transaction()` lives inside the Action, not the controller. Return the persisted model.
- **Controllers** — 3–5 lines per method. Constructor-inject Actions. Call FormRequest, call Policy via `$this->authorize(...)`, call Action, return response. No inline `validator(...)` or `$request->validate()`.
- **FormRequests** — one per write endpoint, under a per-resource subdirectory. Share input normalization via `Http\Requests\Concerns\` traits.
- **Policies** — use `$this->authorize('view', $board)` in controllers. Use `Response::denyAsNotFound()` (not `Response::deny`) when the failure should be a 404 (e.g. resource ownership).
- **Eloquent** — prefer `Model::query()` over `DB::`. Use eager loading. Cast enum-typed columns to the enum class.
- **API responses** — return `ApiResponse::success($data, $message, $status)` or `ApiResponse::error($message, $status, $data)`. Never return bare arrays from API controllers. Never use the Resource-as-envelope pattern.
- **Resources** — `JsonResource` subclasses shape one entity. No `$message`/`$statusCode` constructor args.

## What's deliberately gone (don't reintroduce)

- `App\Repositories\*` — deleted in Phase 3. Use Eloquent directly from Services or Actions.
- Service interfaces with single implementations — generally not needed (`AuthServiceInterface` is the exception, kept for the binding pattern).
- The `ApiResource` envelope class — deleted. Use `ApiResponse`.
- Lazy-ensure side effects on read methods. Read helpers on models (`Board::orderedForUser`, `BoardColumn::statusesForBoard`, etc.) are pure. Setup happens via `EnsureUserHasDefaultBoardAction` (called from `UserObserver` at signup) and `EnsureBoardHasDefaultColumnsAction` (called defensively in board-aware controllers).

## Frontend (Inertia + Vue) layers

```
resources/js/
├── Pages/                          (Inertia page components — thin orchestrators)
├── Components/                     (single-purpose Vue components)
├── composables/                    (reusable stateful logic — use<Thing>.js)
└── lib/                            (pure helpers, no Vue)
    ├── format.js                   (formatters, badge classes, defaultStatusLabels)
    └── task.js                     (task/comment normalizers, grouping)
```

### Conventions

- **Pages** drive Inertia routing. Keep them thin — extract child components and composables aggressively.
- **Components** — modals end in `Modal.vue`. Scoped styles stay with the component (`<style scoped>`).
- **Composables** accept dependencies via an options object; return refs and event handlers. See `useTaskDragDrop`, `useColumnDragDrop`.
- **`lib/`** is pure — no Vue imports. Functions only. Tested-by-being-used.
- **Tailwind content** — `tailwind.config.js` scans both `.vue` AND `.js` under `resources/js/`. If you add Tailwind classes inside a JS module, that's already covered.

## Adding a new feature

1. Migration → model → factory → enum (if a fixed set, cast on model).
2. **Action(s)** for each business operation. One per verb.
3. **FormRequest(s)** for write inputs. Use `Concerns\` traits for shared prepare logic.
4. **Policy** if ownership/access matters. Failures that should hide existence use `denyAsNotFound`.
5. **Controller** — thin, inject Actions.
6. **Route** in `routes/web.php` (Inertia) or `routes/api.php` (`apiResource(...)->only([...])` for partial CRUD).
7. **JsonResource** for API endpoints; **Presenter** for Inertia page payloads.
8. **Feature test** in `tests/Feature/` hitting the route.
9. **Frontend**: Vue component → composable for stateful pieces → `lib/` for pure helpers.

## Running things

This project runs inside Laravel Sail.

- `vendor/bin/sail up -d` — start. If port 3306 is taken by another mysql container, use `FORWARD_DB_PORT=3307 APP_PORT=8080 vendor/bin/sail up -d`.
- `vendor/bin/sail artisan test` — run PHP tests. Filter with `--filter=TaskBoard` or by file path.
- `vendor/bin/sail npm run build` / `vendor/bin/sail npm run dev` — frontend build.
- `vendor/bin/sail bin pint` — format PHP. Run before finalizing.
- `vendor/bin/sail root-shell -c '...'` — for commands that fail with permission errors under the `sail` user (e.g. pint writing files, vite writing temp files).

## Commit style

Conventional Commits prefixes (`feat:`, `fix:`, `chore:`, `refactor:`, `style:`, `docs:`). Lowercase imperative subject, under ~60 chars. No body unless the *why* isn't visible from the diff. **No `Co-Authored-By: Claude` trailer or AI-attribution markers.**
