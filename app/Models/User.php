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

    public function authenticateSuspended(string $email, string $password): bool
    {
        $statement = $this->connection->prepare(
            'SELECT password_hash FROM users WHERE email = :email AND is_active = :is_active LIMIT 1'
        );
        $statement->execute([
            'email' => $email,
            'is_active' => 0,
        ]);
        $passwordHash = $statement->fetchColumn();

        return is_string($passwordHash) && password_verify($password, $passwordHash);
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

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT id, name, email, role, is_active, created_at'
            . ' FROM users ORDER BY created_at DESC, id DESC'
        );

        return $statement->fetchAll();
    }

    public function createManagedAccount(string $name, string $email, string $password, string $role): bool
    {
        if (!in_array($role, ['customer', 'admin'], true)) {
            return false;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)'
            . ' VALUES (:name, :email, :password_hash, :role, 1)'
        );

        try {
            return $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? (string) $exception->getCode()) === '23000') {
                return false;
            }

            throw $exception;
        }
    }

    public function setActive(string $userId, bool $isActive): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET is_active = :is_active'
            . ' WHERE id = :id AND role <> :admin_role'
        );

        return $statement->execute([
            'is_active' => $isActive ? 1 : 0,
            'id' => $userId,
            'admin_role' => 'admin',
        ]) && $statement->rowCount() === 1;
    }

}
