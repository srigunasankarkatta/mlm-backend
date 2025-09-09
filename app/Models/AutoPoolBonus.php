<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoPoolBonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'group_completion_id',
        'auto_pool_level',
        'amount',
        'status',
        'paid_at',
        'payment_reference',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Constants
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function groupCompletion()
    {
        return $this->belongsTo(GroupCompletion::class);
    }

    public function autoPoolLevel()
    {
        return $this->belongsTo(AutoPoolLevel::class, 'auto_pool_level', 'level');
    }

    // Scopes
    public function scopeByLevel($query, $level)
    {
        return $query->where('auto_pool_level', $level);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getFormattedAmount()
    {
        return number_format($this->amount, 2);
    }

    public function getStatusDisplayName()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    public function getPaidDate()
    {
        return $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null;
    }

    public function getFormattedPaidDate()
    {
        return $this->paid_at ? $this->paid_at->format('M d, Y') : null;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function markAsPaid($paymentReference = null)
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $paymentReference
        ]);
    }

    public function markAsFailed($notes = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'notes' => $notes
        ]);
    }

    public function markAsCancelled($notes = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => $notes
        ]);
    }
}
