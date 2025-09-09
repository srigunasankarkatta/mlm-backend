<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'sponsor_id',
        'package_id',
        'referral_code',
        'auto_pool_level',
        'group_completion_count',
        'last_group_completion_at',
        'total_auto_pool_earnings',
        'auto_pool_stats'
    ];

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

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function earningWallet()
    {
        return $this->hasOne(Wallet::class)->where('type', Wallet::TYPE_EARNING);
    }

    public function bonusWallet()
    {
        return $this->hasOne(Wallet::class)->where('type', Wallet::TYPE_BONUS);
    }

    // Auto Pool relationships
    public function groupCompletions()
    {
        return $this->hasMany(GroupCompletion::class);
    }

    public function autoPoolBonuses()
    {
        return $this->hasMany(AutoPoolBonus::class);
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
