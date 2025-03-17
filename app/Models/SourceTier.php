<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceTier extends Model
{
    protected $table = 'source_tiers';
    protected $fillable = [
        'name',
        'slug',
        'interval_value'
    ];
}
