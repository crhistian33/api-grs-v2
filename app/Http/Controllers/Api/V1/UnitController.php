<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UnitRequest;
use App\Http\Resources\V1\OptionsResource;
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
use Tymon\JWTAuth\Facades\JWTAuth;

class UnitController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $units;
    protected $unit;
    protected $trashes;
    protected array $relations = ['center', 'customer', 'createdBy', 'updatedBy', 'shifts'];
    protected array $fields = ['id', 'name'];

    public function index()
    {
        $query = Unit::with($this->relations)->get();
        $this->units = UnitResource::collection($query);
        $this->trashes = $this->getTrashedRecords(Unit::class);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();

        $query = Unit::with($this->relations)->whereHas('customer', function($query) use ($companyIds) {
            $query->whereIn('company_id', $companyIds);
        })->get();
        $this->units = UnitResource::collection($query);
        $this->trashes = Unit::onlyTrashed()->whereHas('customer', function($query) use ($companyIds) {
            $query->whereIn('company_id', $companyIds);
        })->count();

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getTrashed() {
        $units = Unit::with($this->relations)->onlyTrashed()->get();
        $this->units = UnitResource::collection($units);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getTrashedByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();
        $query = Unit::with($this->relations)->onlyTrashed()->whereHas('customer', function($query) use ($companyIds) {
            $query->whereIn('company_id', $companyIds);
        })->get();
        $this->units = UnitResource::collection($query);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptions() {
        $units = Unit::select($this->fields)->get();
        $this->units = OptionsResource::collection($units);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptionsByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();

        $units = Unit::select($this->fields)->whereHas('customer', function($query) use ($companyIds) {
            $query->whereIn('company_id', $companyIds);
        })->get();
        $this->units = OptionsResource::collection($units);

        return $this->successResponse(
            $this->units,
            ApiConstants::LIST_TITLE,
            $this->units->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(UnitRequest $request)
    {
        return DB::transaction(function() use ($request) {
            $user = JWTAuth::parseToken()->authenticate();
            $data = $request->all();
            $data['created_by'] = $user->id;
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
        });
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
            $user = JWTAuth::parseToken()->authenticate();
            $data = $request->all();
            $data['updated_by'] = $user->id;
            $unit->update($data);

            if($request->has('shifts')) {
                $currentShiftIds = $unit->shifts()->pluck('shifts.id')->toArray();
                $newShiftIds = collect($request->shifts)->pluck('id')->toArray();
                $shiftsToRemove = array_diff($currentShiftIds, $newShiftIds);

                foreach ($shiftsToRemove as $shiftId) {
                    $unitShift = UnitShift::where('unit_id', $unit->id)
                                         ->where('shift_id', $shiftId)
                                         ->first();
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

        $this->unit = new UnitResource($unit);
        $unit->delete();

        $this->trashes = $this->getTrashedRecords(Unit::class, $company);

        return $this->successResponse(
            $this->unit,
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
            $itemsDelete = Unit::whereIn('id', $itemsToDelete)->get();
            $this->units = UnitResource::collection($itemsDelete);
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
                $this->units,
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
        $unit = Unit::onlyTrashed()->findOrFail($id);
        $this->unit = new UnitResource($unit);
        $unit->restore();

        return $this->successResponse(
            $this->unit,
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
            $itemsRestore = Unit::whereIn('id', $trashedIds)->get();
            $this->units = UnitResource::collection($itemsRestore);

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    $this->units,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                $this->units,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
