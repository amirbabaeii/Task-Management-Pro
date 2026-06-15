# Task Management Pro

Task Management Pro is a self-hosted collaborative Kanban application built
with Laravel 11, Inertia, and Vue 3. Teams can organize work across boards,
assign human or managed-agent identities, and track execution through task
activity, comments, checklists, deadlines, and notifications.

## Available Features

- Multiple boards with custom, reorderable columns.
- Task creation, editing, ordering, duplication, archiving, and restoration.
- Priorities, deadlines, progress, tags, comments, replies, and checklists.
- Board owners and collaborators with board-scoped access.
- Multiple task assignees, including manager-owned agent profiles.
- Dashboard summaries, upcoming work, workload filters, and activity history.
- In-app assignment, collaboration, reply, and deadline notifications.
- Scheduled daily deadline reminders.
- Sanctum authentication and board-scoped task API endpoints.
- Encrypted manager-owned OpenAI connections with verification and rotation.
- Agent autonomy policies: advisory, approval, and scoped automatic execution.
- Queued agent runs with task context snapshots, structured actions, approval
  controls, retry, notifications, and raw-payload pruning.
- Responsive Inertia and Vue interface with server-side rendering.

Managed agents can be assigned to board tasks and run manually from task
details. Automatic mode is intentionally scoped to comments, checklist
changes, progress, and status changes; title, description, tags, priority, and
deadline edits still require manager approval.

## Requirements

- Docker Desktop or another Docker environment compatible with Laravel Sail.
- PHP 8.2 or 8.3 and Composer when installing dependencies outside Sail.
- Node.js 20 or newer when running frontend tooling outside Sail.

## Setup

```bash
cp .env.example .env
composer install
npm ci
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
```

If the default ports are already occupied:

```bash
FORWARD_DB_PORT=3307 APP_PORT=8080 vendor/bin/sail up -d
```

## Development Services

Run the frontend development server:

```bash
vendor/bin/sail npm run dev
```

Run the queue worker used by notifications and future agent jobs:

```bash
vendor/bin/sail artisan queue:work --queue=agents,default
```

Run the scheduler locally so daily deadline reminders and agent payload pruning
are dispatched:

```bash
vendor/bin/sail artisan schedule:work
```

Production deployments should run persistent queue workers and invoke
`php artisan schedule:run` every minute.

## Verification

```bash
vendor/bin/sail artisan test
npm test
npm run build
vendor/bin/sail bin pint
```

If Docker-owned frontend cache or build files block local commands, fix the
generated directories (`node_modules/.vite`, `public/build`, `bootstrap/ssr`)
or run through a container user that can write them.

## AI Operations

Managers add or rotate their OpenAI key from **AI Settings**. Keys are stored
encrypted and are never serialized through API responses. New agents default to
the `approval` autonomy level; managers can downgrade a run at start time but
cannot elevate it above the agent default.

Relevant environment settings:

```env
AI_AGENT_EXECUTION_ENABLED=true
AI_AGENT_QUEUE=agents
AI_AGENT_QUEUE_TIMEOUT=120
AI_MAX_OUTPUT_TOKENS=4000
AI_CONTEXT_COMMENT_LIMIT=20
AI_CONTEXT_ACTIVITY_LIMIT=30
AI_RUN_RAW_PAYLOAD_RETENTION_DAYS=30
AI_RUN_RETENTION_DAYS=180
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=60
OPENAI_REASONING_EFFORT=low
```

The scheduler runs `agents:prune-run-payloads` daily, clearing old
`context_snapshot` payloads while retaining run rows, action rows, token usage,
statuses, and audit metadata. Run `php artisan agents:prune-run-payloads
--days=30` manually after changing retention.

For deployment, back up the database and `.env`/`APP_KEY` together; encrypted
provider keys cannot be recovered without the application key. Keep queue
workers, scheduler, database backups, and key-rotation procedures in the same
release checklist.

## API

Authenticated task operations are scoped to a board:

```text
GET    /api/v1/boards/{board}/tasks
POST   /api/v1/boards/{board}/tasks
PATCH  /api/v1/boards/{board}/tasks/{task}
```

All endpoints require a Sanctum token and board membership. Task creation and
updates use the same board validation and domain actions as the web interface.

## Roadmap

- Additional AI providers after the OpenAI integration is stable.
- Scheduled autonomous scans and realtime streaming.
- External tools, web search, per-board autonomy, and multi-tenant
  organizations.

## License

Task Management Pro is open-source software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
