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
- Responsive Inertia and Vue interface with server-side rendering.

Managed agents currently provide identity, skills, workload, board membership,
and assignment. AI provider connections and controlled task execution are the
next product milestone; they are not active in this version.

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
vendor/bin/sail artisan queue:work
```

Run the scheduler locally so daily deadline reminders are dispatched:

```bash
vendor/bin/sail artisan schedule:work
```

Production deployments should run persistent queue workers and invoke
`php artisan schedule:run` every minute.

## Verification

```bash
vendor/bin/sail artisan test
vendor/bin/sail root-shell -c 'npm test'
vendor/bin/sail root-shell -c 'npm run build'
vendor/bin/sail bin pint
```

The root shell is only needed when Docker-owned frontend cache or build files
prevent the normal Sail user from writing.

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

- Encrypted manager-owned OpenAI connections.
- Manager-defined agent autonomy policies.
- Queued agent runs with structured actions and approval controls.
- Agent run history, notifications, retention, and operational limits.
- Additional AI providers after the OpenAI integration is stable.

## License

Task Management Pro is open-source software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
