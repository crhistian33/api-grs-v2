<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class ShiftRequest extends BaseRequest
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
                Rule::unique('shifts')->ignore($this->route('shift')),
            ],
            'shortName' => [
                'required',
                Rule::unique('shifts')->ignore($this->route('shift')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.unique' => 'El nombre ingresado ya existe',
            'shortName.required' => 'El nombre corto es requerido',
            'shortName.unique' => 'El nombre corto ingresado ya existe',
        ];
    }
}
