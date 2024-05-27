<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotUserResource;
use App\Models\BotUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;

class UsersController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        $botUsers = BotUser::with('bots')->paginate($perPage);

        return BotUserResource::collection($botUsers);
    }

    public function show(BotUser $user)
    {
        return response()->json($user);
    }

    public function destroy(BotUser $user): \Illuminate\Http\JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'Bot deleted successfully']);
    }
}
