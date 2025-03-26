<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'location',
        'center_id',
        'customer_id',
        'min_assign',
        'created_by',
        'updated_by'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'unit_shifts')
            ->withPivot('id')
            ->using(UnitShift::class);
    }
}
