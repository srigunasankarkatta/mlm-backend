<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoPoolLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'name',
        'bonus_amount',
        'required_package_id',
        'required_directs',
        'required_group_size',
        'is_active',
        'description',
        'sort_order'
    ];

    protected $casts = [
        'bonus_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'completion_details' => 'array'
    ];

    // Relationships
    public function requiredPackage()
    {
        return $this->belongsTo(Package::class, 'required_package_id');
    }

    public function groupCompletions()
    {
        return $this->hasMany(GroupCompletion::class, 'auto_pool_level', 'level');
    }

    public function autoPoolBonuses()
    {
        return $this->hasMany(AutoPoolBonus::class, 'auto_pool_level', 'level');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('level');
    }

    // Constants
    const LEVEL_4_STAR = 4;
    const LEVEL_16_STAR = 16;
    const LEVEL_64_STAR = 64;
    const LEVEL_256_STAR = 256;
    const LEVEL_1024_STAR = 1024;

    // Helper methods
    public function isEligibleForUser(User $user)
    {
        // Check if user has required package
        if ($user->package_id < $this->required_package_id) {
            return false;
        }

        // Check if user has required number of directs
        $directsCount = $user->directs()->where('package_id', '>=', 1)->count();
        return $directsCount >= $this->required_directs;
    }

    public function getFormattedBonusAmount()
    {
        return number_format($this->bonus_amount, 2);
    }

    public function getDisplayName()
    {
        return $this->name . ' (' . $this->level . '-Star)';
    }
}
