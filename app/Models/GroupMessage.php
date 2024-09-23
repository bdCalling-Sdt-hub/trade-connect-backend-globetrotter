<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    use HasFactory;
    protected $fillable=[
        'group_id',
        'sender_id',
        'message',
        'images',
        'is_read',
    ];
}
