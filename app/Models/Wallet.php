<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'pending_balance',
        'withdrawn_balance',
        'is_active',
        'settings'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'withdrawn_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'settings' => 'array'
    ];

    // Wallet types
    const TYPE_EARNING = 'earning';
    const TYPE_BONUS = 'bonus';
    const TYPE_REWARD = 'reward';
    const TYPE_HOLDING = 'holding';
    const TYPE_COMMISSION = 'commission';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getAvailableBalanceAttribute()
    {
        return $this->balance - $this->pending_balance;
    }

    public function getTotalBalanceAttribute()
    {
        return $this->balance + $this->pending_balance;
    }
}
