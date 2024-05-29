<?php

namespace App\Http\Controllers;

use App\Http\Resources\BotResource;
use App\Http\Resources\BotTypesCollection;
use App\Models\Bot;
use App\Models\BotType;
use DateInterval;
use DatePeriod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
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

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:bot_types,id',
            'web_hook' => 'nullable|string',
            'token' => 'required|string|max:255',
            'message' => 'nullable|string',
            'active' => 'required|boolean',
//            'message_image' => 'nullable|image',
        ]);

        if ($request->hasFile('message_image')) {
            $imagePath = $request->file('message_image')->store('public/bots');
            $data['message_image'] = Storage::url($imagePath);
        }

        $bot = Bot::create($data);

        $this->updateWebhook($bot);

        return response()->json(['id' => $bot->id]);
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
//            'message_image' => 'nullable|image',
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
        return new BotTypesCollection($botTypes);
    }

    public function updateWebhook(Bot $bot)
    {
        Log::info('updateWebhook for: ' . $bot->name);

//        $telegram = new Api($bot->token);

//        $webHook = 'https://120c-93-127-105-235.ngrok-free.app/webhook/bot/';

//        $telegram->setWebhook(['url' => $webHook . $bot->name]);
    }

    public function getBotUserData(Bot $bot)
    {
        $endDate = now();
        $startDate = now()->subDays(7);

        $dateRange = new DatePeriod(
            $startDate,
            new DateInterval('P1D'),
            $endDate->addDay()
        );

        $dates = array_fill_keys(
            array_map(
                function ($date) {
                    return $date->format('Y-m-d');
                },
                iterator_to_array($dateRange)
            ),
            0
        );

        $newUsersStats = $dates;
        $bannedUsersStats = $dates;
        $premiumUsersStats = $dates;
        $botId = $bot->id; // Получаем ID бота из модели

        $newUsersData = DB::table('bot_bot_user')
            ->where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        foreach ($newUsersData as $data) {
            $newUsersStats[$data->date] = $data->count;
        }

        $bannedUsersData = DB::table('banned_bot_user_relations')
            ->where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        foreach ($bannedUsersData as $data) {
            $bannedUsersStats[$data->date] = $data->count;
        }

        $premiumUsersData = DB::table('bot_bot_user')
            ->join('bot_users', 'bot_bot_user.bot_user_id', '=', 'bot_users.id')
            ->where('bot_bot_user.bot_id', $botId)
            ->where('bot_users.premium', 1)
            ->whereBetween('bot_bot_user.created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(bot_bot_user.created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        foreach ($premiumUsersData as $data) {
            $premiumUsersStats[$data->date] = $data->count;
        }

        $totalNewUsers = array_sum($newUsersStats);
        $totalBannedUsers = array_sum($bannedUsersStats);
        $totalPremiumUsers = array_sum($premiumUsersStats);
        $totalDefaultUsers = array_sum($newUsersStats) - array_sum($premiumUsersStats);

        return response()->json([
            'new_users' => $newUsersStats,
            'banned_users' => $bannedUsersStats,
            'premium_users' => $premiumUsersStats,
            'total_new_users' => $totalNewUsers,
            'total_banned_users' => $totalBannedUsers,
            'total_premium_users' => $totalPremiumUsers,
            'total_default_users' => $totalDefaultUsers,
        ]);
    }
}
