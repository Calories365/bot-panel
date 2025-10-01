<?php

namespace App\Http\Controllers;

use App\Http\Requests\BotDataRequest;
use App\Http\Resources\AllManagersResource;
use App\Http\Resources\BotResource;
use App\Http\Resources\BotTypesCollection;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Bot;
use App\Models\BotType;
use App\Models\Manager;
use App\Services\BotManagmentService;
use App\Services\BotUsersService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class BotController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected BotManagmentService $botManagmentService;

    public function __construct(BotManagmentService $botManagmentService)
    {
        $this->botManagmentService = $botManagmentService;
    }

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        $bots = Bot::paginate($perPage);

        return BotResource::collection($bots);
    }

    public function show(Bot $bot): BotResource
    {
        return new BotResource($bot);
    }

    public function destroy(Bot $bot): \Illuminate\Http\JsonResponse
    {
        $bot->delete();

        return response()->json(['message' => 'Bot deleted successfully']);
    }

    public function create(BotDataRequest $request): \Illuminate\Http\JsonResponse
    {
        $bot = $this->handleBotData($request);

        return response()->json(['id' => $bot->id]);
    }

    public function update(BotDataRequest $request, Bot $bot): BotResource
    {
        $bot = $this->handleBotData($request, $bot);

        return new BotResource($bot);
    }

    /**
     * Unified logic for creating/updating a bot
     */
    protected function handleBotData(BotDataRequest $request, ?Bot $bot = null): Bot
    {
        $data = $request->validated();

        foreach (['message_image', 'video_ru', 'video_ua', 'video_eng'] as $fileField) {
            if ($request->hasFile($fileField)) {
                $data[$fileField] = $this->botManagmentService->handleFileUpload($request, $fileField);
            }
        }

        if ($bot) {
            $bot->update($data);
        } else {
            $bot = Bot::create($data);
        }

        $this->botManagmentService->syncManagers($request, $bot);
        $bot->updateWeebHook();

        return $bot;
    }

    public function getTypes(): BotTypesCollection
    {
        $botTypes = BotType::all();

        return new BotTypesCollection($botTypes);
    }

    public function getManagers(): AllManagersResource
    {
        return new AllManagersResource(new Manager);
    }

    public function getBotUserData(Bot $bot, BotUsersService $botUsersService): \Illuminate\Http\JsonResponse
    {
        $data = $botUsersService->getBotUserData($bot);

        return response()->json($data);
    }

    public function handle(string $bot, Request $request): JsonResponse
    {
        try {
            $updateId = $request->input('update_id');
            ProcessTelegramUpdate::dispatch(
                $bot,
                $request->all(),
                $updateId !== null ? (int) $updateId : null
            )->onQueue('telegram');

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::critical('Webhook dispatch error', [
                'bot' => $bot,
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'success']);
        }
    }
}
