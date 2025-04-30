<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use SoftDeletes;

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

    protected $casts = [
        'birth_date' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function unitShifts()
    {
        return $this->belongsToMany(UnitShift::class, 'assignments', 'worker_id', 'unit_shift_id')
            ->withPivot('id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function inassists()
    {
        return $this->hasMany(Inassist::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasActiveContract(): bool
    {
        return $this->contracts()
            ->whereDate('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now());
            })
            ->exists();
    }

    public function lastContract()
    {
        return $this->hasOne(Contract::class)->latest('start_date');
    }
}
