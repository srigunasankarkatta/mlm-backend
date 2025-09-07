<?php

namespace App\DTOs\Admin;

class CreatePackageDTO
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
        public readonly int $levelUnlock,
        public readonly ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            price: (float) $data['price'],
            levelUnlock: (int) $data['level_unlock'],
            description: $data['description'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'level_unlock' => $this->levelUnlock,
            'description' => $this->description
        ];
    }
}
