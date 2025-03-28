<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YoutubeVideo extends Model
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'youtube_videos';
    protected $dates = ['deleted_at'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'channel_id', 
        'video_id', 
        'video_url', 
        'title', 
        'description',
        'thumbnail',
        'upload_date', 
        'view_count', 
        'like_count',
        'duration',
        'is_short'
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id', 'channel_id');
    }
}
