<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'code',
        'name',
        'created_by',
        'update_by'
    ];

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
