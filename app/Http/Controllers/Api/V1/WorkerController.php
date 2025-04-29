<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\WorkerContractRequest;
use App\Http\Requests\V1\WorkerRequest;
use App\Http\Resources\V1\WorkerResource;
use App\Models\Company;
use App\Models\Worker;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $workers;
    protected $worker;
    protected $trashes;
    protected array $relations = ['company', 'createdBy', 'updatedBy'];
    protected array $attributes = ['name', 'dni', 'birth_date', 'bank_account', 'company_id'];

    public function index(?Company $company = null)
    {
        $query = Worker::with($this->relations);
        $query = $this->getData($query, $company)->get();
        $this->workers = WorkerResource::collection($query);
        $this->trashes = $this->getTrashedRecords(Worker::class, $company);

        return $this->successResponse(
            $this->workers,
            ApiConstants::LIST_TITLE,
            $this->workers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getTrashed(?Company $company = null) {
        $query = Worker::with($this->relations)->onlyTrashed();
        $query = $this->getData($query, $company)->get();
        $this->workers = WorkerResource::collection($query);

        return $this->successResponse(
            $this->workers,
            ApiConstants::LIST_TITLE,
            $this->workers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(WorkerRequest $request)
    {
        $data = $request->all();
        $worker = Worker::create($data);

        if($request->has('contract')) {
            $worker->contracts()->create($request->input('contract'));
        }

        $this->worker = new WorkerResource($worker);

        return $this->successResponse(
            $this->worker,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Worker $worker)
    {
        $this->worker = new WorkerResource($worker);
        return $this->successResponse(
            $this->worker,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(WorkerRequest $request, Worker $worker)
    {
        $worker->update($request->only($this->attributes));
        if($request->has('contract')) {
            $contract = $worker->lastContract()->first();
            if($contract) {
                $contract->update($request->input('contract'));
            } else {
                $worker->contracts()->create($request->input('contract'));
            }
        }
        $this->worker = new WorkerResource($worker);

        return $this->successResponse(
            $this->worker,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function renew(Worker $worker, WorkerContractRequest $request)
    {
        if($request->has('contract')) {
            $worker->contracts()->create($request->input('contract'));
        }
        $this->worker = new WorkerResource($worker);

        return $this->successResponse(
            $this->worker,
            ApiConstants::RENEW_SUCCESS_TITLE,
            ApiConstants::RENEW_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Worker $worker, ?Company $company = null)
    {
        if($worker->unitShifts()->exists() || $worker->inassists()->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response::HTTP_CONFLICT,
            );
        }
        $worker->delete();
        $this->trashes = $this->getTrashedRecords(Worker::class, $company);

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
        $this->worker = Worker::onlyTrashed()->findOrFail($id);
        $this->worker->forceDelete();
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
            $existingItems = Worker::whereIn('id', $ids)->get();
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
            $itemsWithRelations = $existingItems->filter(function($worker) {
                return $worker->unitShifts()->count() > 0;
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
            Worker::whereIn('id', $itemsToDelete)->delete();

            $this->trashes = $this->getTrashedRecords(Worker::class, $company);

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
            $existingItems = Worker::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            Worker::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $this->worker = Worker::onlyTrashed()->findOrFail($id);
        $this->worker->restore();
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
            $trashedIds = Worker::onlyTrashed()
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

            Worker::onlyTrashed()->whereIn('id', $trashedIds)->restore();

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
