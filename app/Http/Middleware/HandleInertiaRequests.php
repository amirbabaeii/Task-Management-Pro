<?php

namespace App\Http\Middleware;

use App\Models\Board;
use App\Support\Presenters\BoardPresenter;
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
            'unreadNotifications' => fn () => $request->user()
                ? $request->user()->unreadNotifications()->count()
                : 0,
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

        return BoardPresenter::collection(Board::accessibleForUser($user), $user);
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
            : Board::accessibleForUser($user)->first();

        if (! $board) {
            return null;
        }

        return BoardPresenter::navigation($board, $user);
    }
}
