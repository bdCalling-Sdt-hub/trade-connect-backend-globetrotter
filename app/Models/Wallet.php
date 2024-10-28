<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "payment_method",
        "total_love",
        "amount",

    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
