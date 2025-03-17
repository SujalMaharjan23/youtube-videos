<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $table='youtube_channels';
    protected $fillable = [
        'channel_name',
        'channel_id',
        'username',
        'description',
        'channel_logo_url',
        'hidden'
    ];

    public function tier()
    {
        return $this->hasOneThrough(
            \App\Models\SourceTier::class, // Target model
            'youtube_tiers_pivot',         // Pivot table
            'channel_id',                  // Foreign key on pivot
            'id',                          // Foreign key on target
            'channel_id',                  // Local key on Channel
            'tier_id'                      // Local key on pivot
        );
    }
}
