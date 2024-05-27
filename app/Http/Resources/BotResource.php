<?php

namespace App\Http\Resources;

use App\Models\BotType;
use Illuminate\Http\Resources\Json\JsonResource;

class BotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $botTypes = BotType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'active' => (int)$this->type_id === (int)$type->id
            ];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'token' => $this->token,
            'type_id' => (int)$this->type_id,
            'message' => $this->message,
            'message_image' => $this->message_image,
            'active' => (int)$this->active,
            'web_hook' => $this->web_hook,
            'bot_types' => $botTypes
        ];
    }
}
