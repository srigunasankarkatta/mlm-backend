<?php

namespace App\DTOs\Admin;

class UpdateUserDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?int $sponsorId = null,
        public readonly ?int $packageId = null,
        public readonly ?array $roles = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            sponsorId: $data['sponsor_id'] ?? null,
            packageId: $data['package_id'] ?? null,
            roles: $data['roles'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        if ($this->sponsorId !== null) {
            $data['sponsor_id'] = $this->sponsorId;
        }

        if ($this->packageId !== null) {
            $data['package_id'] = $this->packageId;
        }

        if ($this->roles !== null) {
            $data['roles'] = $this->roles;
        }

        return $data;
    }
}
