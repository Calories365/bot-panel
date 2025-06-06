<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $botIds = $this->bots->pluck('id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'telegram_id' => $this->telegram_id,
            'is_banned' => $this->is_banned,
            'phone' => $this->phone,
            'premium' => $this->premium,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'bot_ids' => $botIds,
        ];
    }
}
