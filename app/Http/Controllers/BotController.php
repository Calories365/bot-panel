<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotResource;
use App\Http\Resources\BotTypesResource;
use App\Models\Bot;
use App\Models\BotType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;
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

    public function create(Request $request): BotResource
    {
        // Валидация входных данных
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:bot_types,id',
            'web_hook' => 'nullable|string',
            'token' => 'required|string|max:255',
            'message' => 'nullable|string',
            'active' => 'required|boolean',
            'message_image' => 'nullable|image',
        ]);

        // Обработка загрузки изображения, если файл предоставлен
        if ($request->hasFile('message_image')) {
            $imagePath = $request->file('message_image')->store('public/bots');
            $data['message_image'] = Storage::url($imagePath);
        }

        // Создание нового бота с валидированными данными
        $bot = Bot::create($data);

        // Возвращение созданного бота через BotResource
        return new BotResource($bot);
    }

    public function update(Request $request, Bot $bot): BotResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:bot_types,id',
            'web_hook' => 'string',
            'token' => 'required|string|max:255',
            'message' => 'nullable|string',
            'active' => 'required|boolean',
            'message_image' => 'nullable|image',
        ]);

        if ($request->hasFile('message_image')) {
            $imagePath = $request->file('message_image')->store('public/bots');
            $data['message_image'] = Storage::url($imagePath);
        }

        $bot->update($data);

        return new BotResource($bot);
    }

    public function getTypes()
    {
        $botTypes = BotType::all();
        return BotTypesResource::collection($botTypes);
    }
}
