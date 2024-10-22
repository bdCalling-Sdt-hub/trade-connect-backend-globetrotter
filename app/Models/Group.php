<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable=[
        'name',
        'image',
        'created_by',
        'status',
    ];
    // public function members()
    // {
    //     return $this->belongsToMany(User::class, 'group_members');
    // }
    public function messages()
    {
        return $this->hasMany(GroupMessage::class);
    }
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
