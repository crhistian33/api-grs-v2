<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CompanyRequest;
use App\Http\Resources\V1\CompanyResource;
use App\Http\Resources\V1\OptionsCodeResource;
use App\Models\Company;
use App\Traits\ApiResponse;
use App\Traits\FilterCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyController extends Controller
{
    use ApiResponse;
    use FilterCompany;

    protected $companies;
    protected $company;
    protected $trashes;
    protected array $relations = ['createdBy', 'updatedBy'];
    protected array $fields = ['id', 'code', 'name'];

    public function index()
    {
        $this->companies = Company::with($this->relations)->get();
        $this->trashes = $this->getTrashedRecords(Company::class);

        return $this->successResponse(
            CompanyResource::collection($this->companies),
            ApiConstants::LIST_TITLE,
            $this->companies->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function getTrashed() {
        $this->companies = Company::with($this->relations)->onlyTrashed()->get();
        return $this->successResponse(
            CompanyResource::collection($this->companies),
            ApiConstants::LIST_TITLE,
            $this->companies->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function getOptions() {
        $companies = Company::select($this->fields)->get();
        $this->companies = OptionsCodeResource::collection($companies);
        return $this->successResponse(
            $this->companies,
            ApiConstants::LIST_TITLE,
            $this->companies->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
        );
    }

    public function store(CompanyRequest $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $data = $request->validated();
        $data['created_by'] = $user->id;

        $company = Company::create($data);

        $this->company = new CompanyResource($company);

        return $this->successResponse(
            $this->company,
            ApiConstants::CREATE_SUCCESS_TITLE,
            ApiConstants::CREATE_SUCCESS_MESSAGE,
            Response::HTTP_CREATED
        );
    }

    public function show(Company $company)
    {
        $this->company = new CompanyResource($company);
        return $this->successResponse(
            $this->company,
            ApiConstants::ITEM_TITLE,
            ApiConstants::ITEM_MESSAGE
        );
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $data = $request->validated();
        $data['updated_by'] = $user->id;
        $company->update($data);

        $this->company = new CompanyResource($company);

        return $this->successResponse(
            $this->company,
            ApiConstants::UPDATE_SUCCESS_TITLE,
            ApiConstants::UPDATE_SUCCESS_MESSAGE,
        );
    }

    public function destroy(Company $company)
    {
        if($company->customers()->whereHas('units.unitShifts.assignments')->exists() || $company->customers()->whereHas('units.unitShifts.inassists')->exists()) {
            return $this->errorResponseMessage(
                ApiConstants::UNITSHIFTS_DELETED_ERROR,
                Response:: HTTP_CONFLICT,
            );
        }
        $this->company = new CompanyResource($company);
        $company->delete();
        $this->trashes = $this->getTrashedRecords(Company::class);

        return $this->successResponse(
            $this->company,
            ApiConstants::DELETE_SUCCESS_TITLE,
            ApiConstants::DELETE_SUCCESS_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }

    public function destroyForce($id)
    {
        $this->company = Company::onlyTrashed()->findOrFail($id);
        $this->company->forceDelete();
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
            $existingItems = Company::whereIn('id', $ids)->get();
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
                return $item->customers()->whereHas('units.unitShifts.assignments')->count() > 0 || $item->customers()->whereHas('units.unitShifts.inassists')->count() > 0;
            })->pluck('id')->toArray();

            // Caso 2: Si todos tienen relaciones, no eliminar ninguno
            if (count($itemsWithRelations) === count($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::UNITSHIFTS_DELETED_ALL_ERROR,
                    Response:: HTTP_CONFLICT,
                );
            }

            // Obtener items sin relaciones para eliminar
            $itemsToDelete = array_diff($existingIds, $itemsWithRelations);
            $itemsDelete = Company::whereIn('id', $itemsToDelete)->get();
            $this->companies = CompanyResource::collection($itemsDelete);
            Company::whereIn('id', $itemsToDelete)->delete();

            $this->trashes = $this->getTrashedRecords(Company::class);

            $message = ApiConstants::DELETEALL_SUCCESS_MESSAGE;

            if (!empty($notFoundIds)) {
                $message = ApiConstants::DELETEALL_INCOMPLETE_SUCCESS_MESSAGE;
            }

            if (!empty($itemsWithRelations)) {
                $message = ApiConstants::DELETEALL_RELATIONS_SUCCESS_MESSAGE;
            }

            return $this->successResponse(
                $this->companies,
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
            $existingItems = Company::onlyTrashed()->whereIn('id', $ids)->get();
            $existingIds = $existingItems->pluck('id')->toArray();

            // Caso 1: Si ninguno existe, retornar error
            if(empty($existingIds)) {
                return $this->errorResponseMessage(
                    ApiConstants::DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE,
                    Response:: HTTP_CONFLICT,
                );
            }

            //Eliminar los items existentes
            Company::onlyTrashed()->whereIn('id', $existingIds)->forceDelete();

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
        $company = Company::onlyTrashed()->findOrFail($id);
        $this->company = new CompanyResource($company);
        $company->restore();

        return $this->successResponse(
            $this->company,
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
            $trashedIds = Company::onlyTrashed()
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

            Company::onlyTrashed()->whereIn('id', $trashedIds)->restore();
            $itemsRestore = Company::whereIn('id', $trashedIds)->get();
            $this->companies = CompanyResource::collection($itemsRestore);

            // Caso 2: Si hay IDs no encontrados (restauración parcial)
            if (!empty($notFoundIds)) {
                return $this->successResponse(
                    $this->companies,
                    ApiConstants::RESTORE_SUCCESS_TITLE,
                    ApiConstants::RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE,
                );
            }

            // Caso 3: Si todos existen y fueron restaurados
            return $this->successResponse(
                $this->companies,
                ApiConstants::RESTORE_SUCCESS_TITLE,
                ApiConstants::RESTOREALL_SUCCESS_MESSAGE,
            );
        });
    }
}
