<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'sponsor_id', 'package_id', 'referral_code'];

    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function directs()
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            // Generate unique referral code like REF123XYZ
            $user->referral_code = 'REF' . strtoupper(substr(md5($user->id . time()), 0, 6));
            $user->save();
        });
    }
}
