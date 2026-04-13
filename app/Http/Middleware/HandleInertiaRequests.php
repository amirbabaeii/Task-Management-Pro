<?php

namespace App\Http\Middleware;

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

    private function sharedBoards(Request $request): array
    {
        $user = $request->user();

        if (!$user) {
            return [];
        }

        return Board::orderedForUser($user)
            ->map(fn (Board $board): array => [
                'id' => $board->id,
                'name' => $board->name,
            ])
            ->values()
            ->all();
    }

    private function sharedCurrentBoard(Request $request): ?array
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $boards = Board::orderedForUser($user);
        $routeBoard = $request->route('board');
        $board = $routeBoard instanceof Board &&
            (int) $routeBoard->user_id === (int) $user->id
            ? $routeBoard
            : $boards->first();

        if (!$board) {
            return null;
        }

        return [
            'id' => $board->id,
            'name' => $board->name,
        ];
    }
}
