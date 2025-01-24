<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CaloriesUserResource extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->transform(function ($user) {
            return [
                'id'                => $user->id,
                'name'              => $user->name,
                'username'          => $user->username,
                'telegram_id'       => $user->telegram_id,
                'is_banned'         => $user->is_banned,
                'phone'             => $user->phone,
                'premium'           => $user->premium,
                'premium_calories'  => $user->premium_calories,
                'created_at'        => $user->created_at
                    ? $user->created_at->format('d.m.Y H:i:s')
                    : null,
                'source'            => $user->source,
                'email'             => $user->email,
                'username_calories' => $user->username_calories,
                'bot_type_id'       => 6
            ];
        });
    }
}
