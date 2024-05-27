<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotResource2 extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'token' => $this->token,
            'type_id' => $this->type_id,
            'message' => $this->message,
            'message_image' => $this->message_image,
            'active' => $this->active,
            'web_hook' => $this->web_hook
        ];
    }
}
