<?php

namespace App\Http\Controllers;

use App\Actions\Boards\UpdateBoardFilterPreferencesAction;
use App\Http\Requests\Boards\UpdateBoardFiltersRequest;
use App\Models\Board;
use Illuminate\Http\JsonResponse;

class BoardFilterController extends Controller
{
    public function __construct(
        private readonly UpdateBoardFilterPreferencesAction $updateFilters,
    ) {}

    public function update(UpdateBoardFiltersRequest $request, Board $board): JsonResponse
    {
        $this->authorize('view', $board);

        return response()->json([
            'filters' => $this->updateFilters->execute(
                $board,
                $request->user(),
                $request->validated(),
            ),
        ]);
    }
}
