<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\DiaryApiService;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class StatsMessageHandler
{
    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message)
    {
        $text = $message->getText();

        $chatId = $message->getChat()->getId();

        $botUser = Utilities::hasCaloriesId($chatId);

        $locale = $botUser->locale;

        if (!$botUser){
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Вы должны быть авторизированны!"
            ]);
            return;
        }

        if (str_contains($text, '/stats')) {
            $userId = $message->getFrom()->getId();

            $date = date('Y-m-d');
            $partOfDay = null;
            $commandParts = explode('_', $text);
            if (isset($commandParts[1])) {
                $partOfDay = $commandParts[1];
            }

            $responseArray = $this->diaryApiService->showUserStats($date, $partOfDay, $chatId, $locale);

            if (isset($responseArray['error'])) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при получении данных. Пожалуйста, попробуйте позже.',
                ]);
                return;
            }
            if (!$partOfDay){
                Log::info($partOfDay);
            }
            if ($partOfDay){
                $messageText = $this->formatStatsMessage($responseArray, $date, $partOfDay, $chatId, $telegram);
            } else {
                $messageText = $this->formatTotalStatsMessage($responseArray, $date);
            }

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
            ]);
        }
    }
    protected function formatTotalStatsMessage($meals, $date)
    {
        if (empty($meals)) {
            return "У вас нет записей на дату *{$date}*.";
        }

        $partsOfDay = [
            'morning' => [
                'name' => 'Завтрак',
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
            'dinner' => [
                'name' => 'Обед',
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
            'supper' => [
                'name' => 'Ужин',
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
            ],
        ];

        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        foreach ($meals as $meal) {
            $part = $meal['part_of_day'];
            if (!isset($partsOfDay[$part])) {
                continue;
            }

            $quantityFactor = $meal['quantity'] / 100;

            $calories = $meal['calories'] * $quantityFactor;
            $proteins = $meal['proteins'] * $quantityFactor;
            $fats = $meal['fats'] * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $partsOfDay[$part]['calories'] += $calories;
            $partsOfDay[$part]['proteins'] += $proteins;
            $partsOfDay[$part]['fats'] += $fats;
            $partsOfDay[$part]['carbohydrates'] += $carbohydrates;

            $total['calories'] += $calories;
            $total['proteins'] += $proteins;
            $total['fats'] += $fats;
            $total['carbohydrates'] += $carbohydrates;
        }

        $messageText = "Ваши данные за *{$date}*:\n\n";

        foreach ($partsOfDay as $part) {
            if ($part['calories'] == 0) {
                continue;
            }
            $productArray = [
                [ "Калории", round($part['calories'])],
                [ "Белки", round($part['proteins'])],
                [ "Жиры", round($part['fats'])],
                [ "Углеводы",round( $part['carbohydrates'])],
            ];
            $messageText .= Utilities::generateTableType2($part['name'] , $productArray) . "\n\n";

        }
        $productArray = [
            [ "Калории", round($total['calories'])],
            [ "Белки", round($total['proteins'])],
            [ "Жиры", round($total['fats'])],
            [ "Углеводы",round( $total['carbohydrates'])],
        ];
        $messageText .= Utilities::generateTableType2('Итого за день' , $productArray);

        return $messageText;
    }
    protected function formatStatsMessage($meals, $date, $partOfDay, $chatId, $telegram)
    {
        if (empty($meals)) {
            $partOfDayText = $partOfDay ? "за *{$partOfDay}*" : "на дату *{$date}*";
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "У вас нет записей {$partOfDayText}.",
                'parse_mode' => 'Markdown',
            ]);
            return;
        }

        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        foreach ($meals as $meal) {

            $quantityFactor = $meal['quantity'] / 100;

            $calories = $meal['calories'] * $quantityFactor;
            $proteins = $meal['proteins'] * $quantityFactor;
            $fats = $meal['fats'] * $quantityFactor;
            $carbohydrates = $meal['carbohydrates'] * $quantityFactor;

            $total['calories'] += $calories;
            $total['proteins'] += $proteins;
            $total['fats'] += $fats;
            $total['carbohydrates'] += $carbohydrates;

            $productArray = [
                ["Калории", round($calories)],
                ["Белки", round($proteins)],
                ["Жиры", round($fats)],
                ["Углеводы", round($carbohydrates)],
            ];

            $messageText = Utilities::generateTableType2($meal['name'] . " ({$meal['quantity']}г)", $productArray);

            $inlineKeyboard = [
                [
                    [
                        'text' => 'Удалить',
                        'callback_data' => 'delete_meal_' . $meal['id']
                    ]
                ]
            ];

            $replyMarkup = json_encode(['inline_keyboard' => $inlineKeyboard]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
                'reply_markup' => $replyMarkup,
            ]);
        }

        $productArray = [
            ["Калории", round($total['calories'])],
            ["Белки", round($total['proteins'])],
            ["Жиры", round($total['fats'])],
            ["Углеводы", round($total['carbohydrates'])],
        ];

        $partOfDayName = $this->getPartOfDayName($partOfDay);
        $messageText = Utilities::generateTableType2("Итого за {$partOfDayName}", $productArray);



        return $messageText;
    }

    private function getPartOfDayName($partOfDay)
    {
        switch ($partOfDay) {
            case 'morning':
                return 'Завтрак';
            case 'dinner':
                return 'Обед';
            case 'supper':
                return 'Ужин';
            default:
                return 'день';
        }
    }
}
