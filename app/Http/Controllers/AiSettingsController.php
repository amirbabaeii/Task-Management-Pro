<?php

namespace App\Http\Controllers;

use App\Actions\AiConnections\DeleteOpenAiConnectionAction;
use App\Actions\AiConnections\SaveOpenAiConnectionAction;
use App\Actions\AiConnections\VerifyOpenAiConnectionAction;
use App\Exceptions\Agents\AgentProviderException;
use App\Http\Requests\AiSettings\UpdateOpenAiConnectionRequest;
use App\Support\Presenters\AiProviderConnectionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly SaveOpenAiConnectionAction $saveOpenAiConnection,
        private readonly DeleteOpenAiConnectionAction $deleteOpenAiConnection,
        private readonly VerifyOpenAiConnectionAction $verifyOpenAiConnection,
    ) {}

    public function edit(Request $request): Response
    {
        abort_if($request->user()->is_agent, 404);

        return Inertia::render('Settings/Ai', [
            'connection' => AiProviderConnectionPresenter::toArray(
                $request->user()->openAiConnection,
            ),
        ]);
    }

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

    public function verify(Request $request): JsonResponse
    {
        abort_if($request->user()->is_agent, 404);

        $connection = $request->user()->openAiConnection;

        if ($connection === null) {
            return response()->json([
                'message' => 'Save an OpenAI connection before verifying it.',
            ], 422);
        }

        try {
            $connection = $this->verifyOpenAiConnection->execute($connection);
        } catch (AgentProviderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode->value,
            ], 422);
        }

        return response()->json([
            'connection' => AiProviderConnectionPresenter::toArray($connection),
        ]);
    }
}
