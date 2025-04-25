<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'shortName',
        'created_by',
        'update_by'
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'unit_shifts')
            ->withPivot('id');
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

        static::deleting(function (Shift $shift) {
            if (!$shift->isForceDeleting()) {
                $shift->unitShifts()->get()->each(function (UnitShift $unitshift) {
                    $unitshift->delete();
                });
            }
        });

        static::restoring(function (Shift $shift) {
            $shift->unitShifts()->onlyTrashed()->get()->each(function (UnitShift $unitshift) {
                $unitshift->restore();
            });
        });
    }
}
