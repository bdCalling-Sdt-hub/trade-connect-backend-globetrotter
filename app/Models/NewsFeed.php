<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsFeed extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'share_your_thoughts',
        'images',
        'video_url',
        'privacy',
        'status',
    ];
    public function comments()
    {
        return $this->hasMany(Comment::class,'newsfeed_id');
    }
    public function user()
    {
        return $this->belongsTo(user::class);
    }
    // NewsFeed.php
    public function likes()
    {
        return $this->hasMany(Like::class, 'newsfeed_id');
    }


}
