<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InassistDetail extends Model
{
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
}
