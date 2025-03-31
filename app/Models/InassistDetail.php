<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InassistDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inassist_id',
        'inassist_date',
        'comment',
        'replacement_id',
        'created_by',
        'updated_by'
    ];

    public function inassist()
    {
        return $this->belongsTo(Inassist::class);
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
