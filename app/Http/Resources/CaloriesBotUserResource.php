<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

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
            $calId = $botUser->calories_id;
            $external = $this->diaryData[$calId] ?? null;

            $email   = $external['email'] ?? null;
            $calName = $external['name']  ?? null;

            return [
                'id'             => $botUser->id,
                'name'           => $botUser->name,
                'username'       => $botUser->username,
                'telegram_id'    => $botUser->telegram_id,
                'is_banned'      => $botUser->is_banned,
                'phone'          => $botUser->phone,
                'premium'        => $botUser->premium,
                'premium_calories' => $botUser->premium,
                'created_at'     => $botUser->created_at->format('d.m.Y H:i:s'),
                'source'         => $botUser->source,
                'email'             => $email,
                'username_calories' => $calName,
                'bot_type_id' => 6
            ];
        });
    }
}
