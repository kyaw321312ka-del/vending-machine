<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function create(
        string $name,
        string $email,
        string $passwordHash,
        string $role = 'user'
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, role)
            VALUES (:name, :email, :password, :role)'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'role' => $role,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, role, created_at, updated_at
            FROM users
            WHERE id = :id
            LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password, role, created_at, updated_at
            FROM users
            WHERE email = :email
            LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1
            FROM users
            WHERE email = :email
            LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
            SET password = :password
            WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'password' => $passwordHash,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateRole(int $id, string $role): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
            SET role = :role
            WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'role' => $role,
        ]);

        return $statement->rowCount() > 0;
    }
}
