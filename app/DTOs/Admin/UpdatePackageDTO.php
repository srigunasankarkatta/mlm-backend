<?php

namespace App\DTOs\Admin;

class UpdatePackageDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?float $price = null,
        public readonly ?int $levelUnlock = null,
        public readonly ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            levelUnlock: isset($data['level_unlock']) ? (int) $data['level_unlock'] : null,
            description: $data['description'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->price !== null) {
            $data['price'] = $this->price;
        }

        if ($this->levelUnlock !== null) {
            $data['level_unlock'] = $this->levelUnlock;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
