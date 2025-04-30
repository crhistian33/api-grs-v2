<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class CustomerRequest extends BaseRequest
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
                Rule::unique('customers')->ignore($this->route('customer')),
            ],
            'name' => [
                'required',
                Rule::unique('customers')->ignore($this->route('customer')),
            ],
            'company_id' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es requerido',
            'code.unique' => 'El código ingresado ya existe',
            'name.required' => 'El nombre es requerido',
            'name.unique' => 'El nombre ingresado ya existe',
            'company_id.required' => 'La empresa es requerida',
        ];
    }
}
