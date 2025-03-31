<?php

namespace App\Http\Requests\V1;

class InassistRequest extends BaseRequest
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
