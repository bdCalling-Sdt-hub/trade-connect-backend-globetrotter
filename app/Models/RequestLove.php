<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestLove extends Model
{
    use HasFactory;
    protected $fillable = [
        "amount",
        "status",
        "user_id",
        "request_id",
    ];
    public function requestedBy()
    {
        return $this->belongsTo(User::class,"request_id");
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
