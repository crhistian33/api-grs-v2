<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseRequest extends FormRequest
{
    public function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->all();
        $errorMessage = implode("\n", $errors);

        throw new HttpResponseException(response()->json([
            'success'     => false,
            'status_code' => 422,
            'message'     => $errorMessage,
        ], 422));
    }
}
