<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    protected $fillable = [
        'name',
        'dni',
        'birth_date',
        'bank_account',
        'company_id',
        'type_worker_id',
        'created_by',
        'updated_by'
    ];

    public function typeWorker()
    {
        return $this->belongsTo(TypeWorker::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function unitShifts()
    {
        return $this->belongsToMany(UnitShift::class, 'assignments')
            ->withPivot('id')
            ->using(Assignment::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
