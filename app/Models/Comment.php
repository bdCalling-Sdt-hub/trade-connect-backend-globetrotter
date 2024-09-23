<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    protected $fillable = [
        "newsfeed_id",
        "user_id",
        "parent_id",
        "comments",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // A comment belongs to a newsfeed
    public function newsfeed()
    {
        return $this->belongsTo(Newsfeed::class);
    }

    // A comment may have replies
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // A comment may have a parent comment (if it's a reply)
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}
