<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SourceTier extends Model
{
    use HasFactory;
    
    protected $table = 'source_tiers';

    protected $fillable = [
        'name',
        'slug',
        'interval_value'
    ];
}
