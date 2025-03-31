<?php

namespace App\Http\Requests\V1;

class InassistDetailRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
