<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = ['name','email','password','sponsor_id','package_id'];

    public function sponsor() {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function directs() {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    public function package() {
        return $this->belongsTo(Package::class);
    }

    public function incomes() {
        return $this->hasMany(Income::class);
    }
}
