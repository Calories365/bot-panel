<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotAdminResource;
use App\Models\BotAdmin;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;

class AdminController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        $bots = BotAdmin::paginate($perPage);
        return BotAdminResource::collection($bots);
    }

    public function show(BotAdmin $botAdmin): BotAdminResource
    {
        return new BotAdminResource($botAdmin);
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $botAdmin = BotAdmin::create($data);
        return response()->json(['id' => $botAdmin->id]);
    }

    public function update(Request $request, BotAdmin $botAdmin): BotAdminResource
    {
        $botAdmin->update($request->only(['name', 'telegram_id']));
        return new BotAdminResource($botAdmin);
    }

    public function destroy(BotAdmin $botAdmin): \Illuminate\Http\JsonResponse
    {
        $botAdmin->delete();
        return response()->json(['message' => 'BotAdmin deleted successfully']);
    }
}
