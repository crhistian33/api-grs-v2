<?php

namespace App\Http\Requests\V1;

use Illuminate\Validation\Rule;

class WorkerRequest extends BaseRequest
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
                Rule::unique('workers')->ignore($this->route('worker')),
            ],
            'dni' => [
                'required',
                'max:8',
                Rule::unique('workers')->ignore($this->route('worker')),
            ],
            'birth_date' => 'required',
            'bank_account' => 'required',
            'contract.type_worker_id' => 'required',
            'contract.start_date' => 'required',
            'contract.end_date' => 'required',
            'company_id' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.unique' => 'El nombre ingresado ya existe',
            'dni.required' => 'El DNI es requerido',
            'dni.unique' => 'El DNI ingresado ya existe',
            'dni.max' => 'El DNI puede tener hasta 8 dígitos',
            'birth_date.required' => 'La fecha de nacimiento es requerida',
            'bank_account.required' => 'La cuenta de banco es requerida',
            'contract.type_worker_id.required' => 'El tipo de trabajador es requerido',
            'contract.start_date.required' => 'La fecha de inicio es requerida',
            'contract.end_date.required' => 'La fecha de cese es requerida',
            'company_id.required' => 'La empresa es requerida',
        ];
    }
}
