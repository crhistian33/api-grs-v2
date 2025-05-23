<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CenterRequest;
use App\Http\Resources\V1\CenterResource;
use App\Http\Resources\V1\OptionsCenterResource;
use App\Models\Center;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class CenterController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $centers;
    protected $center;
    protected $trashes;
    protected array $relations = ['createdBy', 'updatedBy'];
    protected array $fields = ['id', 'code', 'name', 'mount'];

    public function index()
    {
        $centers = Center::with($this->relations)->get();
        $this->centers = CenterResource::collection($centers);
        $this->trashes = $this->getTrashedRecords(Center::class);

        return $this->successResponse(
            $this->centers,
            ApiConstants::LIST_TITLE,
            $this->centers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }


    public function getTrashed() {
        $centers = Center::with($this->relations)->onlyTrashed()->get();
        $this->centers = CenterResource::collection($centers);

        return $this->successResponse(
            $this->centers,
            ApiConstants::LIST_TITLE,
            $this->centers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptions() {
        $centers = Center::select($this->fields)->get();
        $this->centers = OptionsCenterResource::collection($centers);

        return $this->successResponse(
            $this->centers,
            ApiConstants::LIST_TITLE,
            $this->centers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(CenterRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['created_by'] = $user->id;
        $center = Center::create($data);
        $this->center = new CenterResource($center);

        return $this->successResponse(
            $this->center,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Center $center)
    {
        $this->center = new CenterResource($center);
        return $this->successResponse(
            $this->center,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(CenterRequest $request, Center $center)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['updated_by'] = $user->id;
        $center->update($data);
        $this->center = new CenterResource($center);

        return $this->successResponse(
            $this->center,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Center $center)
    {
        if($center->units()->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITS_DELETED_ERROR,
                Response:: HTTP_CONFLICT
            );
        }
        $this->center = new CenterResource($center);
        $center->delete();
        $this->trashes = $this->getTrashedRecords(Center::class);

        return $this->successResponse(
            $this->center,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function destroyForce($id)
    {
        $this->center = Center::onlyTrashed()->findOrFail($id);
        $this->center->forceDelete();
        return $this->successResponse(
            null,
            ApiConstants::DELETE_FORCE_SUCCESS_TITLE,
            ApiConstants::DELETE_FORCE_SUCCESS_MESSAGE,
        );
    }

    public function destroyAll(Request $request)
    {
        return DB::transaction(function() use ($request) {
            $ids = collect($request->input('resources'))->pluck('id')->toArray();
            $existingItems = Center::whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();
            $notFoundIds = array_diff($ids, $existingIds);

            // Caso 1: Si ninguno existe, retornar error
            if (empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            // Obtener Items con relaciones
            $itemsWithRelations = $existingItems->filter(function($item) {
                return $item->units()->count() > 0;
            })->pluck('id')->toArray();

            // Caso 2: Si todos tienen relaciones, no eliminar ninguno
            if (count($itemsWithRelations) === count($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::UNITS_DELETED_ALL_ERROR,
                    Response::HTTP_CONFLICT,
                );
            }

            // Obtener items sin relaciones para eliminar
            $itemsToDelete = array_diff($existingIds, $itemsWithRelations);
            $itemsDelete = Center::whereIn('id', $itemsToDelete)->get();
            $this->centers = CenterResource::collection($itemsDelete);
            Center::whereIn('id', $itemsToDelete)->delete();
            $this->trashes = $this->getTrashedRecords(Center::class);

            $message = ApiConstants::DELETEALL_SUCCESS_MESSAGE;

            if (!empty($notFoundIds)) {
                $message = ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE;
            }

            if (!empty($itemsWithRelations)) {
                $message = ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE;
            }

            return $this->successResponse(
                $this->centers,
                ApiConstants::DELETE_SUCCESS_TITLE,
                $message,
                Response::HTTP_OK,
                $this->trashes
            );
        });
    }

    public function destroyForceAll(Request $request)
    {
        return DB::transaction(function() use ($request) {
            $ids = collect($request->input('resources'))->pluck('id')->toArray();
            $existingItems = Center::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            Center::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

            // Caso 2: Si hay IDs no encontrados (eliminación parcial)
            if(count($existingIds) !== count($ids)) {
                return $this->successResponse(
                    null,
                    ApiConstants::DELETE_FORCE_SUCCESS_TITLE,
                    ApiConstants::DELETEALL_FORCE_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron eliminados
            return $this->successResponse(
                null,
                ApiConstants::DELETE_FORCE_SUCCESS_TITLE,
                ApiConstants::DELETEALL_FORCE_SUCCESS_MESSAGE,
            );
        });
    }

    public function restore($id)
    {
        $center = Center::onlyTrashed()->findOrFail($id);
        $this->center = new CenterResource($center);
        $center->restore();

        return $this->successResponse(
            $this->center,
            ApiConstants::RESTORE_SUCCESS_TITLE,
            ApiConstants::RESTORE_SUCCESS_MESSAGE,
        );
    }

    public function restoreAll(Request $request)
    {
        return DB::transaction(function() use ($request) {
            $resources = $request->input('resources');
            $ids = collect($resources)->pluck('id')->toArray();

            // Obteniendo los IDs eliminados
            $trashedIds = Center::onlyTrashed()
                        ->whereIn('id', $ids)
                        ->pluck('id')
                        ->toArray();

            $notFoundIds = array_diff($ids, $trashedIds);

            // Caso 1: Si ninguno existe, retornar error
            if(count($notFoundIds) === count($ids)) {
                return $this->errorResponseMessage(
                    ApiConstants::RESTOREALL_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            Center::onlyTrashed()->whereIn('id', $trashedIds)->restore();
            $itemsRestore = Center::whereIn('id', $trashedIds)->get();
            $this->centers = CenterResource::collection($itemsRestore);

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    $this->centers,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                $this->centers,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
