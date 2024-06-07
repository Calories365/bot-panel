<?php

namespace App\Http\Controllers;

use App\Http\Requests\BotDataRequest;
use App\Http\Resources\BotResource;
use App\Http\Resources\BotTypesCollection;
use App\Models\Bot;
use App\Models\BotType;
use App\Services\BotUsersService;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BotController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request)
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
        $data = $request->validated();

        if ($request->hasFile('message_image')) {
            $imagePath = $request->file('message_image')->store('public/bots');
            $data['message_image'] = Storage::url($imagePath);
        }

        $bot = Bot::create($data);

        return response()->json(['id' => $bot->id]);
    }

    public function update(BotDataRequest $request, Bot $bot): BotResource
    {
        $data = $request->validated();

        if ($request->hasFile('message_image')) {
            $imagePath = $request->file('message_image')->store('public/bots');
            $data['message_image'] = Storage::url($imagePath);
        }

        $bot->update($data);
        $bot->updateWeebHook();

        return new BotResource($bot);
    }

    public function getTypes(): BotTypesCollection
    {
        $botTypes = BotType::all();
        return new BotTypesCollection($botTypes);
    }

    public function getBotUserData(Bot $bot, BotUsersService $botUsersService): \Illuminate\Http\JsonResponse
    {
        $data = $botUsersService->getBotUserData($bot);
        return response()->json($data);
    }

    public function handle(TelegramHandler $telegramHandler, $bot, Request $request): \Illuminate\Http\JsonResponse
    {
        Log::info('start');
        $telegramHandler->handle($bot, $request);
        return response()->json(['status' => 'success']);
    }
}
