<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'reference_id',
        'description',
        'metadata',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Transaction types
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_REFUND = 'refund';
    const TYPE_FEE = 'fee';
    const TYPE_PENALTY = 'penalty';

    // Transaction categories
    const CATEGORY_DIRECT_INCOME = 'direct_income';
    const CATEGORY_LEVEL_INCOME = 'level_income';
    const CATEGORY_CLUB_INCOME = 'club_income';
    const CATEGORY_AUTO_POOL = 'auto_pool';
    const CATEGORY_BONUS = 'bonus';
    const CATEGORY_PACKAGE_PURCHASE = 'package_purchase';
    const CATEGORY_WITHDRAWAL = 'withdrawal';
    const CATEGORY_TRANSFER = 'transfer';
    const CATEGORY_ADMIN_CREDIT = 'admin_credit';
    const CATEGORY_ADMIN_DEBIT = 'admin_debit';
    const CATEGORY_FEE = 'fee';
    const CATEGORY_PENALTY = 'penalty';

    // Transaction statuses
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCredits($query)
    {
        return $query->where('type', self::TYPE_CREDIT);
    }

    public function scopeDebits($query)
    {
        return $query->where('type', self::TYPE_DEBIT);
    }
}
