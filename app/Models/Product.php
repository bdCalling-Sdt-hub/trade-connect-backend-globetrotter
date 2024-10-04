<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_id',
        'user_id',
        'category_id',
        'product_name',
        'price',
        'product_code',
        'images',
        'description',
        'status',
    ];
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class); // Assuming each product belongs to a user
    }
}
