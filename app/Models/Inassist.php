<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inassist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'worker_id',
        'state_worker_id',
        'month',
        'unit_shift_id',
        'created_by',
        'update_by'
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function unitShift()
    {
        return $this->belongsTo(UnitShift::class);
    }

    public function stateWorker()
    {
        return $this->belongsTo(StateWorker::class);
    }

    public function inassistDetails()
    {
        return $this->hasMany(InassistDetail::class);
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
