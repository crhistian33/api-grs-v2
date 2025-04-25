<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UnitRequest;
use App\Http\Resources\V1\UnitResource;
use App\Models\Company;
use App\Models\Unit;
use App\Models\UnitShift;
use App\Models\Assignment;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $units;
    protected $unit;
    protected $trashes;
    protected array $relations = ['center', 'customer', 'createdBy', 'updatedBy', 'shifts'];

    public function index(?Company $company = null)
    {
        $query = Unit::with($this->relations);
        $query = $this->getData($query, $company)->get();
        $this->units = UnitResource::collection($query);
        $this->trashes = $this->getTrashedRecords(Unit::class, $company);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
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
        $unit = Unit::create($data);
        if($request->has('shifts')) {
            $shiftIds = collect($request->shifts)->pluck('id')->toArray();
            $unit->shifts()->attach($shiftIds);
        }
        $this->unit = new UnitResource($unit);

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
        return DB::transaction(function() use ($request, $unit) {
            $unit->update($request->all());
            if($request->has('shifts')) {
                $currentShiftIds = $unit->shifts()->pluck('shifts.id')->toArray();
                //dd($currentShiftIds);
                $newShiftIds = collect($request->shifts)->pluck('id')->toArray();
                //dd($newShiftIds);
                $shiftsToRemove = array_diff($currentShiftIds, $newShiftIds);
                //dd($shiftsToRemove);

                foreach ($shiftsToRemove as $shiftId) {
                    $unitShift = UnitShift::where('unit_id', $unit->id)
                                         ->where('shift_id', $shiftId)
                                         ->first();
                    //dd($unitShift);
                    if ($unitShift) {
                        $hasAssignments = Assignment::where('unit_shift_id', $unitShift->id)->exists();

                        if ($hasAssignments) {
                            $unitShift->delete();
                        } else {
                            $unitShift->forceDelete();
                        }
                    }
                }

                $shiftsToAdd = array_diff($newShiftIds, $currentShiftIds);
                $unit->shifts()->attach($shiftsToAdd);

                //$shiftIds = collect($request->shifts)->pluck('id')->toArray();
                //$unit->shifts()->sync($shiftIds);

                // Primero restauramos los registros soft-deleted que están en la nueva selección
                // foreach($shiftIds as $shiftId) {
                //     $unit->shifts()->withTrashed()
                //         ->wherePivot('shift_id', $shiftId)
                //         ->restore();
                // }

                // // Luego sincronizamos para agregar los nuevos y eliminar los que ya no están
                // $unit->shifts()->sync($shiftIds);

                // $unit->load('shifts');
            }

            $this->unit = new UnitResource($unit);

            return $this->successResponse(
                $this->unit,
                ApiConstants::UPDATE_SUCCESS_TITLE,
                ApiConstants::UPDATE_SUCCESS_MESSAGE,
            );
        });
    }

    public function destroy(Unit $unit, ?Company $company = null)
    {
        if($unit->unitShifts()->whereHas('assignments')->exists() || $unit->unitShifts()->whereHas('inassists')->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response::HTTP_CONFLICT,
            );
        }
        $unit->delete();

        $this->trashes = $this->getTrashedRecords(Unit::class, $company);

        return $this->successResponse(
            null,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
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

    public function destroyAll(Request $request, ?Company $company = null)
    {
        return DB::transaction(function() use ($request, $company) {
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

            $this->trashes = $this->getTrashedRecords(Unit::class, $company);

            $message = ApiConstants::DELETEALL_SUCCESS_MESSAGE;

            if (!empty($notFoundIds)) {
                $message = ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE;
            }

            if (!empty($itemsWithRelations)) {
                $message = ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE;
            }

            return $this->successResponse(
                $itemsToDelete,
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
            null,
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
