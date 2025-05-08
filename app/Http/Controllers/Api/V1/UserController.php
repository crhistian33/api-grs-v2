<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\v1\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    use ApiResponse;

    protected $user;
    protected $users;
    protected $trashes;

    public function index()
    {
        $users = User::with('role')->get();
        $this->users = UserResource::collection($users);
        $this->trashes = User::with('role')->onlyTrashed()->get();

        return $this->successResponse(
            $this->users,
            ApiConstants::LIST_TITLE,
            $this->users->isEmpty() ? ApiConstants::ITEMS_NOT_FOUND : ApiConstants::LIST_MESSAGE,
            Response::HTTP_OK,
            $this->trashes,
        );
    }
}
