<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\DiaryApiService;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

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

        if (str_contains($text, '/stats')) {
            $chatId = $message->getChat()->getId();
            $userId = $message->getFrom()->getId();

            $date = date('Y-m-d');

            $commandParts = explode(' ', $text);
            if (isset($commandParts[1])) {
                $date = $commandParts[1];
            }

            $responseArray = $this->diaryApiService->showUserStats($date);

            if (isset($responseArray['error'])) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при получении данных. Пожалуйста, попробуйте позже.',
                ]);
                return;
            }

            $messageText = $this->formatStatsMessage($responseArray, $date);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    protected function formatStatsMessage($meals, $date)
    {
        if (empty($meals)) {
            return "У вас нет записей на дату *{$date}*.";
        }

        // Инициализируем массивы для каждой части дня
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
}
