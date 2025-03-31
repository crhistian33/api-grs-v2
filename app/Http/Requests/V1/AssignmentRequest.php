<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class AssignmentRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_shift_id' => 'required',
            'created_by' => [
                Rule::when($this->isMethod('POST'), [
                    'required',
                ]),
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'unit_shift_id.required' => 'La unidad por turno es requerida',
            'created_by.required' => 'El usuario es requerido',
        ];
    }
}
