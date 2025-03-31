<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

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

    public function unitShifts(): HasMany
    {
        return $this->hasMany(UnitShift::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
