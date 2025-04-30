<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ShiftRequest;
use App\Http\Resources\V1\OptionsResource;
use App\Http\Resources\V1\ShiftResource;
use App\Models\Shift;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShiftController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $shifts;
    protected $shift;
    protected $trashes;
    protected array $relations = ['createdBy', 'updatedBy'];
    protected array $fields = ['id', 'name'];

    public function index()
    {
        $shifts = Shift::with($this->relations)->get();
        $this->shifts = ShiftResource::collection($shifts);
        $this->trashes = $this->getTrashedRecords(Shift::class);

        return $this->successResponse(
            $this->shifts,
            ApiConstants::LIST_TITLE,
            $this->shifts->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes
        );
    }

    public function getTrashed() {
        $shifts = Shift::with($this->relations)->onlyTrashed()->get();
        $this->shifts = ShiftResource::collection($shifts);
        return $this->successResponse(
            $this->shifts,
            ApiConstants::LIST_TITLE,
            $this->shifts->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptions() {
        $shifts = Shift::select($this->fields)->get();
        $this->shifts = OptionsResource::collection($shifts);

        return $this->successResponse(
            $this->shifts,
            ApiConstants::LIST_TITLE,
            $this->shifts->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(ShiftRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['created_by'] = $user->id;
        $shift = Shift::create($data);
        $this->shift = new ShiftResource($shift);

        return $this->successResponse(
            $this->shift,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Shift $shift)
    {
        $this->shift = new ShiftResource($shift);
        return $this->successResponse(
            $this->shift,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(ShiftRequest $request, Shift $shift)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['updated_by'] = $user->id;
        $shift->update($data);
        $this->shift = new ShiftResource($shift);

        return $this->successResponse(
            $this->shift,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Shift $shift)
    {
        if($shift->unitShifts()->whereHas('assignments')->exists() || $shift->unitShifts()->whereHas('inassists')->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response::HTTP_CONFLICT,
            );
        }

        $this->shift = new ShiftResource($shift);
        $shift->delete();
        $this->trashes = $this->getTrashedRecords(Shift::class);

        return $this->successResponse(
            $this->shift,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function destroyForce($id)
    {
        $this->shift = Shift::onlyTrashed()->findOrFail($id);
        $this->shift->forceDelete();
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
            $existingItems = Shift::whereIn('id', $ids)->get();
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
            $itemsDelete = Shift::whereIn('id', $itemsToDelete)->get();
            $this->shifts = ShiftResource::collection($itemsDelete);
            Shift::whereIn('id', $itemsToDelete)->delete();

            $this->trashes = $this->getTrashedRecords(Shift::class);

            $message = ApiConstants::DELETEALL_SUCCESS_MESSAGE;

            if (!empty($notFoundIds)) {
                $message = ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE;
            }

            if (!empty($itemsWithRelations)) {
                $message = ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE;
            }

            return $this->successResponse(
                $this->shifts,
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
            $existingItems = Shift::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            Shift::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $shift = Shift::onlyTrashed()->findOrFail($id);
        $this->shift = new ShiftResource($shift);
        $shift->restore();
        return $this->successResponse(
            $this->shift,
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
            $trashedIds = Shift::onlyTrashed()
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

            Shift::onlyTrashed()->whereIn('id', $trashedIds)->restore();
            $itemsRestore = Shift::whereIn('id', $trashedIds)->get();
            $this->shifts = ShiftResource::collection($itemsRestore);

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    $this->shifts,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                $this->shifts,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
