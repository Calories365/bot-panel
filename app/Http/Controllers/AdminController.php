<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotAdminResource;
use App\Models\BotAdmin;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

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


    public function show(BotAdmin $botAdmin)
    {
        return response()->json($botAdmin);
    }


    public function destroy(BotAdmin $botAdmin): \Illuminate\Http\JsonResponse
    {
        $botAdmin->delete();
        return response()->json(['message' => 'Bot deleted successfully']);
    }
}
