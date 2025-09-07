<?php

namespace App\DTOs\Admin;

class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $referralCode,
        public readonly ?int $sponsorId = null,
        public readonly ?string $sponsorName = null,
        public readonly ?int $packageId = null,
        public readonly ?string $packageName = null,
        public readonly ?float $packagePrice = null,
        public readonly int $directsCount = 0,
        public readonly float $totalIncome = 0.0,
        public readonly array $roles = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public static function fromModel(\App\Models\User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            referralCode: $user->referral_code,
            sponsorId: $user->sponsor_id,
            sponsorName: $user->sponsor?->name,
            packageId: $user->package_id,
            packageName: $user->package?->name,
            packagePrice: $user->package?->price,
            directsCount: $user->directs()->count(),
            totalIncome: $user->incomes()->sum('amount'),
            roles: $user->roles->pluck('name')->toArray(),
            createdAt: $user->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $user->updated_at?->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'referral_code' => $this->referralCode,
            'sponsor' => $this->sponsorId ? [
                'id' => $this->sponsorId,
                'name' => $this->sponsorName
            ] : null,
            'package' => $this->packageId ? [
                'id' => $this->packageId,
                'name' => $this->packageName,
                'price' => $this->packagePrice ? number_format($this->packagePrice, 2) : null
            ] : null,
            'directs_count' => $this->directsCount,
            'total_income' => number_format($this->totalIncome, 2),
            'roles' => $this->roles,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}
