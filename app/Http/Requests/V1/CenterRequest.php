<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class CenterRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                Rule::unique('centers')->ignore($this->route('center')),
            ],
            'name' => [
                'required',
                Rule::unique('centers')->ignore($this->route('center')),
            ],
            'mount' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es requerido',
            'code.unique' => 'El código ingresado ya existe',
            'name.required' => 'El nombre es requerido',
            'name.unique' => 'El nombre ingresado ya existe',
            'mount.required' => 'El monto es requerido',
        ];
    }
}
