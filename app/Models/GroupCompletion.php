<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'auto_pool_level',
        'group_size',
        'directs_count',
        'total_network_size',
        'bonus_amount',
        'bonus_paid',
        'completed_at',
        'completion_details'
    ];

    protected $casts = [
        'bonus_amount' => 'decimal:2',
        'bonus_paid' => 'boolean',
        'completed_at' => 'datetime',
        'completion_details' => 'array'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function autoPoolLevel()
    {
        return $this->belongsTo(AutoPoolLevel::class, 'auto_pool_level', 'level');
    }

    public function autoPoolBonuses()
    {
        return $this->hasMany(AutoPoolBonus::class);
    }

    // Scopes
    public function scopeByLevel($query, $level)
    {
        return $query->where('auto_pool_level', $level);
    }

    public function scopePaid($query)
    {
        return $query->where('bonus_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('bonus_paid', false);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('completed_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getFormattedBonusAmount()
    {
        return number_format($this->bonus_amount, 2);
    }

    public function getCompletionDate()
    {
        return $this->completed_at->format('Y-m-d H:i:s');
    }

    public function getFormattedCompletionDate()
    {
        return $this->completed_at->format('M d, Y');
    }

    public function isPaid()
    {
        return $this->bonus_paid;
    }

    public function markAsPaid()
    {
        $this->update(['bonus_paid' => true]);
    }

    public function getCompletionDetails()
    {
        return $this->completion_details ?? [];
    }

    public function setCompletionDetails(array $details)
    {
        $this->update(['completion_details' => $details]);
    }
}
