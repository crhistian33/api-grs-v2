<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UnitRequest;
use App\Http\Resources\V1\UnitResource;
use App\Models\Company;
use App\Models\Unit;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    use ApiResponse;

    protected $units;
    protected $unit;
    protected array $relations = ['center', 'customer', 'createdBy', 'updatedBy', 'shifts'];

    public function index()
    {
        $this->units = Unit::with($this->relations)->get();
        return $this->successResponse(
            UnitResource::collection($this->units),
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getByCompany(Company $company) {
        $this->units = Unit::with($this->relations)
            ->whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->get();
        return $this->successResponse(
            UnitResource::collection($this->units),
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getTrashed() {
        $this->units = Unit::with($this->relations)->onlyTrashed()->get();
        return $this->successResponse(
            UnitResource::collection($this->units),
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(UnitRequest $request)
    {
        $data = $request->all();
        $this->unit = Unit::create($data);
        if($request->has('shifts')) {
            $shiftIds = collect($request->shifts)->pluck('id')->toArray();
            $this->unit->shifts()->attach($shiftIds);
        }

        return $this->successResponse(
            $this->unit,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Unit $unit)
    {
        $this->unit = new UnitResource($unit);
        return $this->successResponse(
            $this->unit,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(UnitRequest $request, Unit $unit)
    {
        $unit->update($request->all());
        if($request->has('shifts')) {
            $shiftIds = collect($request->shifts)->pluck('id')->toArray();
            $unit->shifts()->sync($shiftIds);
        }
        $this->unit = new UnitResource($unit);

        return $this->successResponse(
            $this->unit,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Unit $unit)
    {
        if($unit->unitShifts()->whereHas('assignments')->exists() || $unit->unitShifts()->whereHas('inassists')->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response::HTTP_CONFLICT,
            );
        }
        $unit->delete();
        return $this->successResponse(
            null,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
        );
    }

    public function destroyForce($id)
    {
        $this->unit = Unit::onlyTrashed()->findOrFail($id);
        $this->unit->forceDelete();
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
            $existingItems = Unit::whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();
            $notFoundIds = array_diff($ids, $existingIds);

            // Caso 1: Si ninguno existe, retornar error
            if (empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            // Obtener items con relaciones
            $itemsWithRelations = $existingItems->filter(function($item) {
                return $item->unitShifts()->whereHas('assignments')->count() > 0 || $item->unitShifts()->whereHas('inassists')->count() > 0;
            })->pluck('id')->toArray();

            // Caso 2: Si todos tienen relaciones, no eliminar ninguno
            if (count($itemsWithRelations) === count($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::UNITSHIFTS_DELETED_ALL_ERROR,
                    Response::HTTP_CONFLICT,
                );
            }

            // Obtener items sin relaciones para eliminar
            $itemsToDelete = array_diff($existingIds, $itemsWithRelations);
            Unit::whereIn('id', $itemsToDelete)->delete();

            // Caso 3: Si hay IDs no encontrados (eliminación parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(null, ApiConstants::DELETE_SUCCESS_TITLE,
                    ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE);
            }

            // Caso 4: Si todos existen pero algunos tienen relaciones
            if (!empty($workersWithRelations)) {
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
            $existingItems = Unit::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            Unit::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $this->unit = Unit::onlyTrashed()->findOrFail($id);
        $this->unit->restore();
        return $this->successResponse(
            new UnitResource($this->unit),
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
            $trashedIds = Unit::onlyTrashed()
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

            Unit::onlyTrashed()->whereIn('id', $trashedIds)->restore();

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
