<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'shortName',
        'created_by',
        'update_by'
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'unit_shifts')
            ->withPivot('id')
            ->using(UnitShift::class);
    }
}
