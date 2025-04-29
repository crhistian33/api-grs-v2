<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\v1\auth\AuthController as AuthV1;
use App\Http\Controllers\Api\v1\TypeWorkerController as TypeWorkerV1;
use App\Http\Controllers\Api\v1\WorkerController as WorkerV1;
use App\Http\Controllers\Api\v1\CenterController as CenterV1;
use App\Http\Controllers\Api\v1\CompanyController as CompanyV1;
use App\Http\Controllers\Api\v1\CustomerController as CustomerV1;
use App\Http\Controllers\Api\v1\UnitController as UnitV1;
use App\Http\Controllers\Api\v1\ShiftController as ShiftV1;
use App\Http\Controllers\Api\v1\UnitShiftController as UnitShiftV1;
use App\Http\Controllers\Api\v1\AssignmentController as AssignmentV1;

Route::post('v1/auth/login', [AuthV1::class, 'login']);
Route::post('v1/auth/refresh', [AuthV1::class, 'refreshToken']);

Route::group(['middleware' => 'jwt'], function () {
    Route::get('v1/auth/logout', [AuthV1::class, 'logout']);

    Route::get('/v1/type_workers/gettrashed', [TypeWorkerV1::class, 'getTrashed']);
    Route::delete('/v1/type_workers/destroy/{type_worker}', [TypeWorkerV1::class, 'destroyForce']);
    Route::post('/v1/type_workers/destroyes', [TypeWorkerV1::class, 'destroyAll']);
    Route::post('/v1/type_workers/destroyesforce', [TypeWorkerV1::class, 'destroyForceAll']);
    Route::get('/v1/type_workers/restore/{type_worker}', [TypeWorkerV1::class, 'restore']);
    Route::post('/v1/type_workers/restores', [TypeWorkerV1::class, 'restoreAll']);

    Route::get('/v1/workers/getbycompany/{company}', [WorkerV1::class, 'getByCompany']);
    Route::get('/v1/workers/gettrashed', [WorkerV1::class, 'getTrashed']);
    Route::patch('/v1/workers/renew/{worker}', [WorkerV1::class, 'renew']);
    Route::delete('/v1/workers/destroy/{worker}', [WorkerV1::class, 'destroyForce']);
    Route::post('/v1/workers/destroyes', [WorkerV1::class, 'destroyAll']);
    Route::post('/v1/workers/destroyesforce', [WorkerV1::class, 'destroyForceAll']);
    Route::get('/v1/workers/restore/{worker}', [WorkerV1::class, 'restore']);
    Route::post('/v1/workers/restores', [WorkerV1::class, 'restoreAll']);

    Route::get('/v1/centers/gettrashed', [CenterV1::class, 'getTrashed']);
    Route::delete('/v1/centers/destroy/{center}', [CenterV1::class, 'destroyForce']);
    Route::post('/v1/centers/destroyes', [CenterV1::class, 'destroyAll']);
    Route::post('/v1/centers/destroyesforce', [CenterV1::class, 'destroyForceAll']);
    Route::get('/v1/centers/restore/{center}', [CenterV1::class, 'restore']);
    Route::post('/v1/centers/restores', [CenterV1::class, 'restoreAll']);

    Route::get('/v1/companies/gettrashed', [CompanyV1::class, 'getTrashed']);
    Route::delete('/v1/companies/destroy/{company}', [CompanyV1::class, 'destroyForce']);
    Route::post('/v1/companies/destroyes', [CompanyV1::class, 'destroyAll']);
    Route::post('/v1/companies/destroyesforce', [CompanyV1::class, 'destroyForceAll']);
    Route::get('/v1/companies/restore/{company}', [CompanyV1::class, 'restore']);
    Route::post('/v1/companies/restores', [CompanyV1::class, 'restoreAll']);

    Route::get('/v1/customers/getbycompany/{company}', [CustomerV1::class, 'index']);
    Route::get('/v1/customers/gettrashed', [CustomerV1::class, 'getTrashed']);
    Route::delete('/v1/customers/destroy/{customer}', [CustomerV1::class, 'destroyForce']);
    Route::post('/v1/customers/destroyes', [CustomerV1::class, 'destroyAll']);
    Route::post('/v1/customers/destroyesforce', [CustomerV1::class, 'destroyForceAll']);
    Route::get('/v1/customers/restore/{customer}', [CustomerV1::class, 'restore']);
    Route::post('/v1/customers/restores', [CustomerV1::class, 'restoreAll']);

    Route::get('/v1/units/getbycompany/{company}', [UnitV1::class, 'index']);
    Route::get('/v1/units/gettrashed', [UnitV1::class, 'getTrashed']);
    Route::delete('/v1/units/destroy/{unit}', [UnitV1::class, 'destroyForce']);
    Route::post('/v1/units/destroyes', [UnitV1::class, 'destroyAll']);
    Route::post('/v1/units/destroyesforce', [UnitV1::class, 'destroyForceAll']);
    Route::get('/v1/units/restore/{unit}', [UnitV1::class, 'restore']);
    Route::post('/v1/units/restores', [UnitV1::class, 'restoreAll']);

    Route::get('/v1/shifts/gettrashed', [ShiftV1::class, 'getTrashed']);
    Route::delete('/v1/shifts/destroy/{shift}', [ShiftV1::class, 'destroyForce']);
    Route::post('/v1/shifts/destroyes', [ShiftV1::class, 'destroyAll']);
    Route::post('/v1/shifts/destroyesforce', [ShiftV1::class, 'destroyForceAll']);
    Route::get('/v1/shifts/restore/{shift}', [ShiftV1::class, 'restore']);
    Route::post('/v1/shifts/restores', [ShiftV1::class, 'restoreAll']);

    Route::apiResources([
        'v1/type_workers' => TypeWorkerV1::class,
        'v1/workers' => WorkerV1::class,
        'v1/centers' => CenterV1::class,
        'v1/companies' => CompanyV1::class,
        'v1/customers' => CustomerV1::class,
        'v1/units' => UnitV1::class,
        'v1/shifts' => ShiftV1::class,
        'v1/assignments' => AssignmentV1::class
    ]);
});

