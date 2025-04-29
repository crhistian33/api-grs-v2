<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class WorkerContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'contract.type_worker_id' => 'required',
            'contract.start_date' => 'required',
            'contract.end_date' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'contract.type_worker_id.required' => 'El tipo de trabajador es requerido',
            'contract.start_date.required' => 'La fecha de inicio es requerida',
            'contract.end_date.required' => 'La fecha de cese es requerida',
        ];
    }
}
