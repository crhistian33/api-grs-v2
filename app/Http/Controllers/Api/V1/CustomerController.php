<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CustomerRequest;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Company;
use App\Models\Customer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    use ApiResponse;

    protected $customers;
    protected $customer;
    protected array $relations = ['company', 'createdBy', 'updatedBy'];

    public function index()
    {
        $this->customers = Customer::with($this->relations)->get();
        return $this->successResponse(
            CustomerResource::collection($this->customers),
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getByCompany(Company $company) {
        $this->customers = Customer::with($this->relations)
            ->where('company_id', $company->id)
            ->get();
        return $this->successResponse(
            CustomerResource::collection($this->customers),
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getTrashed() {
        $this->customers = Customer::with($this->relations)->onlyTrashed()->get();
        return $this->successResponse(
            CustomerResource::collection($this->customers),
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(CustomerRequest $request)
    {
        $data = $request->all();
        $this->customer = Customer::create($data);

        return $this->successResponse(
            $this->customer,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Customer $customer)
    {
        $this->customer = new CustomerResource($customer);
        return $this->successResponse(
            $this->customer,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE,
        );
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->customer = $customer->update($request->all());
        return $this->successResponse(
            $this->customer,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Customer $customer)
    {
        if($customer->units()->whereHas('unitShifts.assignments')->exists() || $customer->units()->whereHas('unitShifts.inassists')->exists()) {
            return $this->errorResponseNotFound(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
            );
        }
        $customer->delete();
        return $this->successResponse(
            null,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
        );
    }

    public function destroyForce($id)
    {
        $this->customer = Customer::onlyTrashed()->findOrFail($id);
        $this->customer->forceDelete();
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
            $existingItems = Customer::whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();
            $notFoundIds = array_diff($ids, $existingIds);

            // Caso 1: Si ninguno existe, retornar error
            if (empty($existingIds)) {
                return $this->errorResponseNotFound(ApiConstants::DELETEALL_NOTFOUND_SUCCESS_MESSAGE);
            }

            // Obtener items con relaciones
            $itemsWithRelations = $existingItems->filter(function($item) {
                return $item->units()->whereHas('unitShifts.assignments')->count() > 0 || $item->units()->whereHas('unitShifts.inassists')->count() > 0;
            })->pluck('id')->toArray();

            // Caso 2: Si todos tienen relaciones, no eliminar ninguno
            if (count($itemsWithRelations) === count($existingIds)) {
                return $this->errorResponseNotFound(ApiConstants::UNITSHIFTS_DELETED_ALL_ERROR);
            }

            // Obtener items sin relaciones para eliminar
            $itemsToDelete = array_diff($existingIds, $itemsWithRelations);
            Customer::whereIn('id', $itemsToDelete)->delete();

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
            $existingItems = Customer::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseNotFound(ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE);
            }

            //Eliminar los items existentes
            Customer::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $this->customer = Customer::onlyTrashed()->findOrFail($id);
        $this->customer->restore();
        return $this->successResponse(
            new CustomerResource($this->customer),
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
            $trashedIds = Customer::onlyTrashed()
                        ->whereIn('id', $ids)
                        ->pluck('id')
                        ->toArray();

            $notFoundIds = array_diff($ids, $trashedIds);

            // Caso 1: Si ninguno existe, retornar error
            if(count($notFoundIds) === count($ids)) {
                return $this->errorResponseNotFound(
                    ApiConstants::RESTOREALL_NOTFOUND_SUCCESS_MESSAGE,
                );
            }

            Customer::onlyTrashed()->whereIn('id', $trashedIds)->restore();

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
