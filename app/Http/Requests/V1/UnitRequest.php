<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class UnitRequest extends BaseRequest
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
                Rule::unique('units')->ignore($this->route('unit')),
            ],
            'name' => [
                'required',
                Rule::unique('units')->ignore($this->route('unit')),
            ],
            'center_id' => 'required',
            'customer_id' => 'required',
            'min_assign' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es requerido',
            'code.unique' => 'El código ingresado ya existe',
            'name.required' => 'El nombre es requerido',
            'name.unique' => 'El nombre ingresado ya existe',
            'center_id.required' => 'El centro de costo es requerido',
            'customer_id.required' => 'El cliente es requerido',
            'min_assign.required' => 'El número de trabajadores a asignar es requerido',
        ];
    }
}
