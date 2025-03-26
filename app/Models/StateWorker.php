<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateWorker extends Model
{
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
}
