<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeWorker extends Model
{
    protected $fillable = [
        'name',
        'created_by',
        'updated_by'
    ];

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }
}
