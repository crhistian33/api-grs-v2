<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeWorker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'created_by',
        'updated_by'
    ];

    public function workers()
    {
        return $this->hasMany(Worker::class);
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
