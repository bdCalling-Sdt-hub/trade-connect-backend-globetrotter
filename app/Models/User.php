<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Notification;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'verify_email',
        'verification_token',
        'otp',
        'otp_expires_at',
        'otp_verified_at',
        'image',
        'role',
        'google_id',
        'facebook_id',
        'is_active',
        'status',
        'user_name',
        'address',
        'location',
        'privacy',
    ];
    protected $casts = [
        'otp_expires_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }


    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'user_id', 'follower_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'user_id');
    }
    public function shops()
    {
        return $this->hasMany(Shop::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function newsFeeds()
    {
        return $this->hasMany(NewsFeed::class, 'user_id');
    }
    public function friends()
    {
        return $this->belongsToMany(User::class, 'friends', 'user_id', 'friend_id')
                    ->wherePivot('is_accepted', true);
    }
    public function friendRequests()
    {
        return $this->hasMany(Friend::class, 'friend_id')->where('is_accepted', false);
    }
    public function likes()
    {
        return $this->hasMany(Like::class);
    }
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
    public function members()
{
    return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id');
}




}
