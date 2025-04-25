<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitShift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unit_id',
        'shift_id'
    ];

    public $incrementing = true;
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function workers()
    {
        return $this->belongsToMany(Worker::class, 'assignments', 'worker_id', 'unit_shift_id')
            ->withPivot('id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function inassists()
    {
        return $this->hasMany(Inassist::class);
    }
}
