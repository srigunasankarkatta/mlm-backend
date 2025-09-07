<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['name', 'price', 'level_unlock', 'direct_income_rate', 'level_income_rate', 'club_income_rate'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
