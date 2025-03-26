<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitShift extends Model
{
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
}
