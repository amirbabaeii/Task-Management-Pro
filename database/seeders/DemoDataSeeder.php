<?php

namespace Database\Seeders;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Enums\BoardRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Board;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const USERS = [
        ['name' => 'Test User', 'email' => 'test@example.com'],
        ['name' => 'Maya Chen', 'email' => 'maya@example.com'],
        ['name' => 'Noah Brooks', 'email' => 'noah@example.com'],
        ['name' => 'Leila Haddad', 'email' => 'leila@example.com'],
        ['name' => 'Owen Patel', 'email' => 'owen@example.com'],
        ['name' => 'Sofia Rivera', 'email' => 'sofia@example.com'],
    ];

    private const BOARDS = [
        [
            'owner' => 'test@example.com',
            'name' => 'Product Launch',
            'description' => 'Launch plan, content, QA, and release follow-up.',
            'members' => [
                'test@example.com',
                'maya@example.com',
                'noah@example.com',
                'leila@example.com',
            ],
            'tasks' => 18,
        ],
        [
            'owner' => 'maya@example.com',
            'name' => 'Design Review',
            'description' => 'Visual polish, interaction review, and design debt.',
            'members' => [
                'maya@example.com',
                'test@example.com',
                'sofia@example.com',
            ],
            'tasks' => 12,
        ],
        [
            'owner' => 'noah@example.com',
            'name' => 'Support Queue',
            'description' => 'Customer requests, fixes, and follow-up tasks.',
            'members' => [
                'noah@example.com',
                'leila@example.com',
                'owen@example.com',
                'sofia@example.com',
            ],
            'tasks' => 14,
        ],
        [
            'owner' => 'leila@example.com',
            'name' => 'Operations Backlog',
            'description' => 'Internal workflow cleanup and recurring operations.',
            'members' => [
                'leila@example.com',
                'test@example.com',
                'owen@example.com',
            ],
            'tasks' => 10,
        ],
    ];

    private const TAGS = [
        'api',
        'billing',
        'copy',
        'design',
        'frontend',
        'infra',
        'mobile',
        'qa',
        'release',
        'research',
        'support',
        'ux',
    ];

    public function __construct(
        private readonly EnsureBoardHasDefaultColumnsAction $ensureColumns,
        private readonly CreateTaskAction $createTask,
    ) {}

    public function run(): void
    {
        /** @var Collection<string, User> $users */
        $users = collect(self::USERS)
            ->mapWithKeys(fn (array $user): array => [
                $user['email'] => $this->firstOrCreateUser($user),
            ]);

        $users->each(fn (User $user): User => $this->assignDefaultRole($user));

        foreach (self::BOARDS as $boardData) {
            $owner = $users->get($boardData['owner']);

            if (! $owner instanceof User) {
                continue;
            }

            $board = $this->firstOrCreateBoard($owner, $boardData);
            $members = $users->only($boardData['members'])->values();

            $this->syncBoardMembers($board, $members);
            $this->ensureColumns->execute($board);
            $this->seedTasksForBoard($board, $members, $boardData['tasks']);
        }
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    private function firstOrCreateUser(array $data): User
    {
        $existing = User::query()
            ->where('email', $data['email'])
            ->first();

        if ($existing instanceof User) {
            return $existing;
        }

        return User::withoutEvents(fn (): User => User::factory()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(self::PASSWORD),
        ]));
    }

    private function assignDefaultRole(User $user): User
    {
        $role = Role::query()
            ->where('name', 'normal-user')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null && ! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * @param  array{name: string, description: string}  $data
     */
    private function firstOrCreateBoard(User $owner, array $data): Board
    {
        $board = Board::query()
            ->where('user_id', $owner->id)
            ->where('name', $data['name'])
            ->first();

        if ($board instanceof Board) {
            return $board;
        }

        return Board::factory()->create([
            'user_id' => $owner->id,
            'name' => $data['name'],
            'description' => $data['description'],
            'position' => Board::nextPositionForUser($owner),
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function syncBoardMembers(Board $board, Collection $members): void
    {
        $joinedAt = now();

        $board->members()->syncWithoutDetaching(
            $members->mapWithKeys(fn (User $member): array => [
                $member->id => [
                    'role' => (int) $member->id === (int) $board->user_id
                        ? BoardRole::Owner->value
                        : BoardRole::Collaborator->value,
                    'joined_at' => $joinedAt,
                ],
            ])->all(),
        );
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function seedTasksForBoard(Board $board, Collection $members, int $count): void
    {
        if ($this->boardHasAssignedTasks($board) || $members->isEmpty()) {
            return;
        }

        for ($index = 0; $index < $count; $index++) {
            $status = fake()->randomElement(TaskStatus::cases());
            $assigneeIds = $members
                ->shuffle()
                ->take(fake()->numberBetween(1, min(3, $members->count())))
                ->pluck('id')
                ->map(fn (int $id): int => $id)
                ->values()
                ->all();

            $task = $this->createTask->execute(
                creator: $members->random(),
                board: $board,
                data: [
                    'title' => fake()->sentence(fake()->numberBetween(3, 7)),
                    'description' => fake()->paragraph(fake()->numberBetween(2, 4)),
                    'status' => $status->value,
                    'priority' => fake()->randomElement(TaskPriority::values()),
                    'progress' => $this->progressForStatus($status),
                    'deadline_at' => fake()->optional(0.82)->dateTimeBetween('-1 week', '+6 weeks'),
                    'tags' => $this->randomTags(),
                    'assignee_ids' => $assigneeIds,
                ],
            );

            $this->maybeArchiveTask($task);
            $this->maybeAttachReviewer($board, $task, $members, $assigneeIds);
            $this->maybeAddComments($task, $members);
        }
    }

    private function boardHasAssignedTasks(Board $board): bool
    {
        return DB::table('task_user')
            ->where('board_id', $board->id)
            ->where('role', 'assignee')
            ->exists();
    }

    private function progressForStatus(TaskStatus $status): int
    {
        return match ($status) {
            TaskStatus::Completed => 100,
            TaskStatus::InProgress => fake()->numberBetween(20, 85),
            TaskStatus::Pending => fake()->numberBetween(0, 15),
        };
    }

    /**
     * @return list<string>
     */
    private function randomTags(): array
    {
        return collect(self::TAGS)
            ->shuffle()
            ->take(fake()->numberBetween(0, 3))
            ->values()
            ->all();
    }

    private function maybeArchiveTask(Task $task): void
    {
        if (! fake()->boolean(12)) {
            return;
        }

        $task->forceFill([
            'archived_at' => fake()->dateTimeBetween('-3 weeks', 'now'),
        ])->save();
    }

    /**
     * @param  Collection<int, User>  $members
     * @param  list<int>  $assigneeIds
     */
    private function maybeAttachReviewer(
        Board $board,
        Task $task,
        Collection $members,
        array $assigneeIds,
    ): void {
        if (! fake()->boolean(35)) {
            return;
        }

        $reviewer = $members
            ->reject(fn (User $member): bool => in_array($member->id, $assigneeIds, true))
            ->shuffle()
            ->first();

        if (! $reviewer instanceof User) {
            return;
        }

        $task->users()->syncWithoutDetaching([
            $reviewer->id => [
                'board_id' => $board->id,
                'role' => 'reviewer',
                'sort_order' => 0,
            ],
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function maybeAddComments(Task $task, Collection $members): void
    {
        if (! fake()->boolean(45)) {
            return;
        }

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $members->random()->id,
            'content' => fake()->paragraph(),
        ]);

        if (! fake()->boolean(35)) {
            return;
        }

        TaskComment::create([
            'task_id' => $task->id,
            'parent_id' => $comment->id,
            'user_id' => $members->random()->id,
            'content' => fake()->sentence(fake()->numberBetween(8, 14)),
        ]);
    }
}
