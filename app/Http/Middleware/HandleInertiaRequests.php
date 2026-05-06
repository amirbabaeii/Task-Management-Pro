<?php

namespace App\Http\Middleware;

use App\Enums\BoardRole;
use App\Models\Board;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'boards' => fn () => $this->sharedBoards($request),
            'currentBoard' => fn () => $this->sharedCurrentBoard($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * @return list<array{id: int, name: string, description: string|null, role: string}>
     */
    private function sharedBoards(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        return $user->accessibleBoards()
            ->orderByRaw("CASE board_members.role WHEN 'owner' THEN 0 ELSE 1 END")
            ->orderBy('boards.name')
            ->get()
            ->map(fn (Board $board): array => [
                'id' => $board->id,
                'name' => $board->name,
                'description' => $board->description,
                'role' => $board->pivot->role,
            ])
            ->values()
            ->all();
    }

    private function sharedCurrentBoard(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $routeBoard = $request->route('board');
        $board = $routeBoard instanceof Board && $routeBoard->hasMember($user)
            ? $routeBoard
            : $user->accessibleBoards()
                ->orderByRaw("CASE board_members.role WHEN 'owner' THEN 0 ELSE 1 END")
                ->orderBy('boards.name')
                ->first();

        if (! $board) {
            return null;
        }

        return [
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'role' => $board->isOwnedBy($user)
                ? BoardRole::Owner->value
                : BoardRole::Collaborator->value,
            'is_owner' => $board->isOwnedBy($user),
        ];
    }
}
