<?php

namespace App\DTOs\Admin;

class PackageDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price,
        public readonly int $levelUnlock,
        public readonly ?string $description = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {}

    public static function fromModel(\App\Models\Package $package): self
    {
        return new self(
            id: $package->id,
            name: $package->name,
            price: $package->price,
            levelUnlock: $package->level_unlock,
            description: $package->description ?? null,
            createdAt: $package->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $package->updated_at?->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => number_format($this->price, 2),
            'level_unlock' => $this->levelUnlock,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
}
