<?php

namespace App\Http\Controllers;

use App\Actions\AiConnections\DeleteOpenAiConnectionAction;
use App\Actions\AiConnections\SaveOpenAiConnectionAction;
use App\Http\Requests\AiSettings\UpdateOpenAiConnectionRequest;
use App\Support\Presenters\AiProviderConnectionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly SaveOpenAiConnectionAction $saveOpenAiConnection,
        private readonly DeleteOpenAiConnectionAction $deleteOpenAiConnection,
    ) {}

    public function update(UpdateOpenAiConnectionRequest $request): JsonResponse
    {
        $connection = $this->saveOpenAiConnection->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'connection' => AiProviderConnectionPresenter::toArray($connection),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_if($request->user()->is_agent, 404);

        $connection = $request->user()->openAiConnection;

        if ($connection !== null) {
            $this->deleteOpenAiConnection->execute($connection);
        }

        return response()->json([
            'connection' => AiProviderConnectionPresenter::toArray(null),
        ]);
    }
}
