<?php

namespace App\Http\Controllers;

use App\Http\Requests\BotDataRequest;
use App\Http\Resources\BotResource;
use App\Http\Resources\BotTypesCollection;
use App\Models\Bot;
use App\Models\BotType;
use App\Services\TelegramServices\TelegramHandler;
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
        $botId = $bot->id;

        $newUsersData = DB::table('bot_user_bot')
            ->where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        foreach ($newUsersData as $data) {
            $newUsersStats[$data->date] = $data->count;
        }

        $bannedUsersData = DB::table('bot_user_bans')
            ->where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        foreach ($bannedUsersData as $data) {
            $bannedUsersStats[$data->date] = $data->count;
        }

        $premiumUsersData = DB::table('bot_user_bot')
            ->join('bot_users', 'bot_user_bot.bot_user_id', '=', 'bot_users.id')
            ->where('bot_user_bot.bot_id', $botId)
            ->where('bot_users.premium', 1)
            ->whereBetween('bot_user_bot.created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(bot_user_bot.created_at) as date'), DB::raw('count(*) as count'))
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
//    public function getBotUserData(Bot $bot, BotUserService $botUserService)
//    {
//        $endDate = now();
//        $startDate = now()->subDays(7);
//
//        $statistics = $botUserService->getBotUserStatistics($bot, $startDate, $endDate);
//
//        return response()->json($statistics);
//    }

    public function handle(TelegramHandler $telegramHandler, $bot, Request $request): \Illuminate\Http\JsonResponse
    {
        Log::info('start');
        $telegramHandler->handle($bot, $request);
        return response()->json(['status' => 'success']);
    }
}
