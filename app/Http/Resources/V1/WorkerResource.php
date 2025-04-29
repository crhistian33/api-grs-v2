<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dni' => $this->dni,
            'birth_date' => $this->birth_date->format('d/m/Y'),
            'bank_account' => $this->bank_account,
            'start_date' => $this->lastContract ? $this->lastContract->start_date->format('d/m/Y') : '',
            'end_date' => $this->lastContract ? $this->lastContract->end_date->format('d/m/Y') : '',
            'typeworker' => $this->lastContract ? $this->lastContract->typeWorker : [],
            'company' => $this->company,
            'state' => $this->hasActiveContract() ? 'Activo' : 'Cesado',
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
