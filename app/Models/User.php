<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

final class User
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function createCustomer(string $name, string $email, string $password): bool
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)'
            . ' VALUES (:name, :email, :password_hash, :role, :is_active)'
        );

        try {
            return $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'customer',
                'is_active' => 1,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) === '23000') {
                return false;
            }

            throw $exception;
        }
    }

    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, email, password_hash, role'
            . ' FROM users WHERE email = :email AND is_active = :is_active LIMIT 1'
        );
        $statement->execute([
            'email' => $email,
            'is_active' => 1,
        ]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->connection->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id'
            );
            $update->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }

        return [
            'id' => (string) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
    }

    public function isActiveAdministrator(string $userId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM users WHERE id = :id AND role = :role AND is_active = :is_active LIMIT 1'
        );
        $statement->execute([
            'id' => $userId,
            'role' => 'admin',
            'is_active' => 1,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
