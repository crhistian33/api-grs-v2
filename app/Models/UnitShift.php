<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitShift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unit_id',
        'shift_id'
    ];

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
        return $this->belongsToMany(Worker::class, 'assignments')
            ->withPivot('id')
            ->using(Assignment::class);
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
