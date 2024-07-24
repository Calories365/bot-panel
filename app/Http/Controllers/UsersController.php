<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotUserResource;
use App\Models\BotUser;
use App\Services\BotUsersService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

class UsersController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request): \Illuminate\Http\Resources\Json\ResourceCollection
    {
        $perPage = $request->input('per_page', 10);
        $botId = $request->input('botId');

        $botUsers = BotUser::getPaginatedUsers($perPage, $botId);

        return BotUserResource::collection($botUsers);
    }

    public function show(BotUser $user): \Illuminate\Http\JsonResponse
    {
        return response()->json($user);
    }

    public function destroy(BotUser $user): \Illuminate\Http\JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function export(Request $request, BotUsersService $botUsersService): \Illuminate\Http\JsonResponse
    {
        $botId = $request->query('botId');
        $content = $botUsersService->exportUsers($botId);

        if (empty($content)) {
            $content = "No users with valid usernames found.";
        }

        $fileName = 'usernames.txt';
        $botUsersService->saveToFile($content, $fileName);

        $downloadUrl = route('file.download', ['filename' => $fileName]);

        return response()->json([
            'message' => 'Export successful',
            'downloadUrl' => $downloadUrl
        ]);
    }
}
