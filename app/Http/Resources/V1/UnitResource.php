<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'center_id' => $this->center->id,
            'center' => new CenterResource($this->center),
            'customer_id' => $this->customer->id,
            'customer' => new CustomerResource($this->customer),
            'min_assign' => $this->min_assign,
            'shifts' => ShiftResource::collection($this->shifts),
            'Created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
