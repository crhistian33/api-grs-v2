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
        'min_assign',
        'center_id',
        'customer_id',
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
            ->withPivot('id');
            //->whereNull('unit_shifts.deleted_at');
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

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Unit $unit) {
            if (!$unit->isForceDeleting()) {
                $unit->unitShifts()->get()->each(function (UnitShift $unitshift) {
                    $unitshift->delete();
                });
            }
        });

        static::restoring(function (Unit $unit) {
            $unit->unitShifts()->onlyTrashed()->get()->each(function (UnitShift $unitshift) {
                $unitshift->restore();
            });
        });
    }
}
