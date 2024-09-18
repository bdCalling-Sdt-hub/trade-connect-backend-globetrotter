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
}
