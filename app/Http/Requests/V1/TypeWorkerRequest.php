<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class TypeWorkerRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('type_workers')->ignore($this->route('typeworker')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            "name.required" => "El nombre es requerido",
            'name.unique' => 'El nombre ingresado ya existe',
        ];
    }
}
