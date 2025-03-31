<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Center extends Model
{
    use SoftDeletes;

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
