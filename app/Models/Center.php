<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Center extends Model
{
    protected $fillable = [
        'code',
        'name',
        'mount',
        'created_by',
        'update_by'
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
