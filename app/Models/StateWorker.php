<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StateWorker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'shortName',
        'isInassist',
        'created_by',
        'updated_by'
    ];

    public function inassists()
    {
        return $this->hasMany(Inassist::class);
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
