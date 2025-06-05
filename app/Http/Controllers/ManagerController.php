<?php

namespace App\Http\Controllers;

use App\Http\Resources\ManagerResource;
use App\Models\Manager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller as BaseController;

class ManagerController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        $bots = Manager::paginate($perPage);

        return ManagerResource::collection($bots);
    }

    public function show(Manager $manager): ManagerResource
    {
        return new ManagerResource($manager);
    }

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $manager = Manager::create($data);

        return response()->json(['id' => $manager->id]);
    }

    public function update(Request $request, Manager $manager): ManagerResource
    {
        $manager->update($request->only(['name', 'telegram_id']));

        return new ManagerResource($manager);
    }

    public function destroy(Manager $manager): \Illuminate\Http\JsonResponse
    {
        $manager->delete();

        return response()->json(['message' => 'Managerted successfully']);
    }
}
