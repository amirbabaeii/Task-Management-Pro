<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Support\Presenters\DashboardPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
    ) {}

    public function index(Request $request): Response
    {
        $defaultBoard = $this->ensureUserHasDefaultBoard->execute($request->user());
        $this->ensureBoardHasDefaultColumns->execute($defaultBoard);

        return Inertia::render('Dashboard', [
            'dashboard' => DashboardPresenter::forUser($request->user()),
        ]);
    }
}
