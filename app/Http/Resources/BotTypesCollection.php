<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BotTypesCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request)
    {
        $firstTypeId = $this->collection->first() ? $this->collection->first()->id : null;

        return [
            'type_id' => $firstTypeId,
            'types' => $this->collection->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name
                ];
            })->all()
        ];
    }
}
