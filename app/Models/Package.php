<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model {
    protected $fillable = ['name','price','level_unlock'];

    public function users() {
        return $this->hasMany(User::class);
    }
}
