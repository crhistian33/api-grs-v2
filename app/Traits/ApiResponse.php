<?php

namespace App\Traits;

use App\Constants\ApiConstants;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

trait ApiResponse
{
    public function successResponse($data = null, $title =null, $message = null, $code = Response::HTTP_OK, $trashes = 0) {
        return response()->json([
            'success' => true,
            'data' => $data,
            'title' => $title,
            'message' => $message,
            'trashes' => $trashes,
        ], $code);
    }

    public function errorResponse(Throwable $exception) {
        //return get_class($exception);
        $errorDetails = $this->getErrorDetails($exception);

        return response()->json([
            'success' => false,
            'message' => $errorDetails['message'],
            'status' => $errorDetails['code'],
        ], $errorDetails['code']);
    }

    public function errorResponseMessage($message = null, $code = null) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'status' => $code,
        ], $code);
    }

    private function getErrorDetails($exception): array
    {
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            $errorMessage = $exception->errorInfo[2] ?? '';
            $nameTable = $this->getTableFromErrorMessage($errorMessage);

            $message = match ($nameTable) {
                'type_workers' => ApiConstants::TYPEWORKERS_DELETED_ERROR,
                'unit_shifts' => ApiConstants::UNITSHIFTS_DELETED_ERROR,
                'state_workers' => ApiConstants::STATEWORKERS_DELETED_ERROR,
                'roles' => ApiConstants::ROLES_DELETED_ERROR,
                default => ApiConstants::DEFAULT_DELETED_ERROR,
            };

            return [
                'message' => $message,
                'code' => Response::HTTP_CONFLICT
            ];
        }

        if ($exception instanceof ModelNotFoundException) {
            return [
                'message' => ApiConstants::NOTFOUND_DELETED_ERROR,
                'code' => Response::HTTP_NOT_FOUND
            ];
        }

        if ($exception instanceof TokenInvalidException) {
            return [
                'message' => ApiConstants::TOKEN_INVALID,
                'code' => Response::HTTP_UNAUTHORIZED
            ];
        }

        if ($exception instanceof TokenExpiredException) {
            return [
                'message' => ApiConstants::TOKEN_EXPIRED,
                'code' => Response::HTTP_UNAUTHORIZED
            ];
        }

        if($exception instanceof JWTException) {
            return [
                'message' => ApiConstants::TOKEN_UNAUTHORIZED,
                'code' => Response::HTTP_UNAUTHORIZED
            ];
        }

        // Manejo de errores HTTP
        if ($exception instanceof HttpException) {
            return match ($exception->getStatusCode()) {
                403 => [
                    'message' => ApiConstants::FORBIDDEN_DELETED_ERROR,
                    'code' => Response::HTTP_FORBIDDEN
                ],
                404 => [
                    'message' => ApiConstants::NOTFOUND_DELETED_ERROR,
                    'code' => Response::HTTP_NOT_FOUND
                ],
                default => [
                    'message' => $exception->getMessage() ?: ApiConstants::INTERNAL_DELETED_ERROR,
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR
                ]
            };
        }

        // Manejo de errores generales
        return [
            'message' => $exception->getMessage() ?: ApiConstants::GENERAL_DELETED_ERROR,
            'code' => Response::HTTP_INTERNAL_SERVER_ERROR
        ];
    }

    private function getTableFromErrorMessage(string $errorMessage): ?string
    {
        preg_match('/REFERENCES `(.*?)`/', $errorMessage, $matches);
        return $matches[1] ?? null;
    }
}
