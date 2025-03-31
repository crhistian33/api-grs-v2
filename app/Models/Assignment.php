<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
