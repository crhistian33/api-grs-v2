<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Constants\ApiConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RefreshTokenRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'name' => 'required|string|between:2,100',
        //     'email' => 'required|string|email|unique:users',
        //     'password' => 'required|string|confirmed|min:6',
        // ]);

        // if($validator->fails()){
        //     return response()->json($validator->errors(), 400);
        // }

        // $user = User::create(array_merge(
        //             $validator->validated(),
        //             ['password' => bcrypt($request->password)]
        //         ));

        // return response()->json([
        //     'message' => 'User successfully registered',
        //     'user' => $user
        // ], 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! $token = JWTAuth::attempt($credentials)) {
            return $this->errorResponseMessage(
                ApiConstants::LOGIN_NOT_CREDENTIALES,
                Response::HTTP_UNAUTHORIZED);
        }

        $user = JWTAuth::user();
        $refreshToken = $this->createRefreshToken($user);

        $data = [
            'access_token' => $token,
            'refresh_token' => $refreshToken,
        ];

        return $this->successResponse(
            $data,
            ApiConstants::LOGIN_TITLE,
            ApiConstants::LOGIN_MESSAGE,
        );
    }

    public function refreshToken(RefreshTokenRequest $request)
    {
        $refreshToken = $request->validated()['refresh_token'];

        $user = JWTAuth::setToken($refreshToken)->authenticate();

        if (!$user) {
            return $this->errorResponseMessage(
                ApiConstants::USER_NOT_FOUND,
                Response::HTTP_NOT_FOUND,
            );
        }

        $newAccessToken = JWTAuth::fromUser($user);
        $newRefreshToken = $this->createRefreshToken($user);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
            ]
        ]);
    }

    public function logout()
    {
        $token = JWTAuth::getToken();

        if(! $token) {
            return $this->errorResponseMessage(
                ApiConstants::TOKEN_INVALID,
                Response::HTTP_UNAUTHORIZED
            );
        }

        JWTAuth::invalidate($token);

        return $this->successResponse(
            null,
            ApiConstants::LOGOUT_SUCCESS_TITLE,
            ApiConstants::LOGOUT_SUCCESS_MESSAGE,
        );
    }

    protected function createRefreshToken($user)
    {
        $refreshTTL = config('jwt.refresh_ttl');

        $payload = [
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + ($refreshTTL * 60),
        ];

        return JWTAuth::customClaims($payload)->fromUser($user);
    }
}
