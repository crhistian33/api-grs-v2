<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TypeWorkerRequest;
use App\Http\Resources\V1\TypeWorkerResource;
use App\Models\TypeWorker;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TypeWorkerController extends Controller
{
    use ApiResponse;

    protected $typeworkers;
    protected $typeworker;
    protected array $relations = ['createdBy', 'updatedBy'];

    public function index()
    {
        $this->typeworkers = TypeWorker::with($this->relations)->get();
        return $this->successResponse(
            TypeWorkerResource::collection($this->typeworkers),
            ApiConstants::LIST_TITLE,
            $this->typeworkers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getTrashed() {
        $this->typeworkers = TypeWorker::with($this->relations)->onlyTrashed()->get();
        return $this->successResponse(
            TypeWorkerResource::collection($this->typeworkers),
            ApiConstants::LIST_TITLE,
            $this->typeworkers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(TypeWorkerRequest $request)
    {
        $data = $request->all();
        $this->typeworker = TypeWorker::create($data);

        return $this->successResponse(
            $this->typeworker,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(TypeWorker $type_worker)
    {
        $this->typeworker = new TypeWorkerResource($type_worker);
        return $this->successResponse(
            $this->typeworker,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(TypeWorkerRequest $request, TypeWorker $typeWorker)
    {
        $typeWorker->update($request->all());
        return $this->successResponse(
            $typeWorker,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(TypeWorker $typeWorker)
    {
        if($typeWorker->workers()->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::TYPEWORKERS_DELETED_ERROR,
                Response::HTTP_CONFLICT,
            );
        }
        $typeWorker->delete();
        return $this->successResponse(
            null,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
        );
    }

    public function destroyForce($id)
    {
        $this->typeworker = TypeWorker::onlyTrashed()->findOrFail($id);
        $this->typeworker->forceDelete();
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
            $existingItems = TypeWorker::whereIn('id', $ids)->get();
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
                return $item->workers()->count() > 0;
            })->pluck('id')->toArray();

            // Caso 2: Si todos tienen relaciones, no eliminar ninguno
            if (count($itemsWithRelations) === count($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::TYPEWORKERS_DELETED_ALL_ERROR,
                    Response::HTTP_CONFLICT,
                );
            }

            // Obtener items sin relaciones para eliminar
            $itemsToDelete = array_diff($existingIds, $itemsWithRelations);
            TypeWorker::whereIn('id', $itemsToDelete)->delete();

            // Caso 3: Si hay IDs no encontrados (eliminación parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(null, ApiConstants::DELETE_SUCCESS_TITLE,
                    ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE);
            }

            // Caso 4: Si todos existen pero algunos tienen relaciones
            if (!empty($itemsWithRelations)) {
                return $this->successResponse(null, ApiConstants::DELETE_SUCCESS_TITLE,
                    ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE);
            }

            // Caso 5: Si todos existen y ninguno tiene relaciones (todos eliminados)
            return $this->successResponse(null, ApiConstants::DELETE_SUCCESS_TITLE,
                ApiConstants::DELETEALL_SUCCESS_MESSAGE);
        });
    }

    public function destroyForceAll(Request $request)
    {
        return DB::transaction(function() use ($request) {
            $ids = collect($request->input('resources'))->pluck('id')->toArray();
            $existingItems = TypeWorker::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            TypeWorker::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $this->typeworker = TypeWorker::onlyTrashed()->findOrFail($id);
        $this->typeworker->restore();
        return $this->successResponse(
            new TypeWorkerResource($this->typeworker),
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
            $trashedIds = TypeWorker::onlyTrashed()
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

            TypeWorker::onlyTrashed()->whereIn('id', $trashedIds)->restore();

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    null,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                null,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
