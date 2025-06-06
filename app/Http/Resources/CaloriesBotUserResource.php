<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Пример коллекции, которая объединяет
 * локальные данные BotUser и внешние данные из дневника калорий
 */
class CaloriesBotUserResource extends ResourceCollection
{
    protected array $diaryData = [];

    public function __construct($resource, array $diaryData = [])
    {
        parent::__construct($resource);
        $this->diaryData = $diaryData;
    }

    /**
     * Преобразуем коллекцию пользователей в массив
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($botUser) {
            return [
                'id' => $botUser->id,
                'name' => $botUser->name,
                'username' => $botUser->username,
                'telegram_id' => $botUser->telegram_id,
                'is_banned' => $botUser->is_banned,
                'phone' => $botUser->phone,
                'premium' => $botUser->premium,
                'premium_calories' => $botUser->premium_calories,
                'created_at' => $botUser->created_at->format('d.m.Y H:i:s'),
                'source' => $botUser->source,
                'email' => $botUser->email,
                'username_calories' => $botUser->username_calories,
                'bot_type_id' => 6,
            ];
        });
    }
}
