<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CustomerRequest;
use App\Http\Resources\V1\CustomerResource;
use App\Http\Resources\V1\OptionsCodeResource;
use App\Models\Company;
use App\Models\Customer;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $customers;
    protected $customer;
    protected $trashes;
    protected array $relations = ['company', 'createdBy', 'updatedBy'];
    protected array $fields = ['id', 'name', 'code'];

    public function index()
    {
        $query = Customer::with($this->relations)->get();
        $this->customers = CustomerResource::collection($query);
        $this->trashes = $this->getTrashedRecords(Customer::class);

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();

        $query = Customer::with($this->relations)->whereIn('company_id', $companyIds)->get();
        $this->customers = CustomerResource::collection($query);
        $this->trashes = Customer::onlyTrashed()->whereIn('company_id', $companyIds)->count();

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getTrashed() {
        $query = Customer::with($this->relations)->onlyTrashed()->get();
        $this->customers = CustomerResource::collection($query);

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getTrashedByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();
        $query = Customer::with($this->relations)->onlyTrashed()->whereIn('company_id', $companyIds)->get();
        $this->customers = CustomerResource::collection($query);

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptions() {
        $customers = Customer::select($this->fields)->get();
        $this->customers = OptionsCodeResource::collection($customers);

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptionsByCompany() {
        $user = JWTAuth::parseToken()->authenticate();
        $companyIds = $user->companies->pluck('id')->toArray();

        $customers = Customer::select($this->fields)->whereIn('company_id', $companyIds)->get();
        $this->customers = OptionsCodeResource::collection($customers);

        return $this->successResponse(
            $this->customers,
            ApiConstants::LIST_TITLE,
            $this->customers->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(CustomerRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['created_by'] = $user->id;
        $customer = Customer::create($data);
        $this->customer = new CustomerResource($customer);

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
        $user = JWTAuth::parseToken()->authenticate();
        $data = $request->all();
        $data['updated_by'] = $user->id;
        $customer->update($data);
        $this->customer = new CustomerResource($customer);

        return $this->successResponse(
            $this->customer,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Customer $customer, ?Company $company = null)
    {
        if($customer->units()->whereHas('unitShifts.assignments')->exists() || $customer->units()->whereHas('unitShifts.inassists')->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response:: HTTP_CONFLICT,
            );
        }

        $this->customer = new CustomerResource($customer);
        $customer->delete();
        $this->trashes = $this->getTrashedRecords(Customer::class, $company);

        return $this->successResponse(
            $this->customer,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
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

    public function destroyAll(Request $request, ?Company $company = null)
    {
        return DB::transaction(function() use ($request, $company) {
            $ids = collect($request->input('resources'))->pluck('id')->toArray();
            $existingItems = Customer::whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();
            $notFoundIds = array_diff($ids, $existingIds);

            // Caso 1: Si ninguno existe, retornar error
            if (empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT
                );
            }

            // Obtener items con relaciones
            $itemsWithRelations = $existingItems->filter(function($item) {
                return $item->units()->whereHas('unitShifts.assignments')->count() > 0 || $item->units()->whereHas('unitShifts.inassists')->count() > 0;
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
            $itemsDelete = Customer::whereIn('id', $itemsToDelete)->get();
            $this->customers = CustomerResource::collection($itemsDelete);
            Customer::whereIn('id', $itemsToDelete)->delete();

            $this->trashes = $this->getTrashedRecords(Customer::class, $company);

            $message = ApiConstants::DELETEALL_SUCCESS_MESSAGE;

            if (!empty($notFoundIds)) {
                $message = ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE;
            }

            if (!empty($itemsWithRelations)) {
                $message = ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE;
            }

            return $this->successResponse(
                $this->customers,
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
            $existingItems = Customer::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response::HTTP_CONFLICT,
                );
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
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $this->customer = new CustomerResource($customer);
        $customer->restore();

        return $this->successResponse(
            $this->customer,
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
                return $this->errorResponseMessage(
                    ApiConstants::RESTOREALL_NOTFOUND_SUCCESS_MESSAGE,
                    Response:: HTTP_CONFLICT,
                );
            }

            Customer::onlyTrashed()->whereIn('id', $trashedIds)->restore();
            $itemsRestore = Customer::whereIn('id', $trashedIds)->get();
            $this->customers = CustomerResource::collection($itemsRestore);

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    $this->customers,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                $this->customers,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
