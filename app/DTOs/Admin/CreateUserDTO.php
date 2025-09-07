<?php

namespace App\DTOs\Admin;

class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $sponsorId = null,
        public readonly ?int $packageId = null,
        public readonly array $roles = ['customer']
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            sponsorId: $data['sponsor_id'] ?? null,
            packageId: $data['package_id'] ?? null,
            roles: $data['roles'] ?? ['customer']
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'sponsor_id' => $this->sponsorId,
            'package_id' => $this->packageId,
            'roles' => $this->roles
        ];
    }
}
