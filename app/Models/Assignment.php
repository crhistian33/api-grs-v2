<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'state',
        'unit_shift_id',
        'created_by',
        'updated_by'
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function unitShift()
    {
        return $this->belongsTo(UnitShift::class);
    }
}
