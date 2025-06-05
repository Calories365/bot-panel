<?php

namespace App\Http\Resources;

use App\Models\Manager;
use Illuminate\Http\Resources\Json\JsonResource;

class AllManagersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $allManagers = Manager::all()->map(function ($manager) {
            return [
                'id' => $manager->id,
                'name' => $manager->name,
            ];
        });

        $botManagers = [];

        return [
            'managers' => $botManagers,
            'allManagers' => $allManagers,
        ];
    }
}
