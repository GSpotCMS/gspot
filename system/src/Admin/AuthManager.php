<?php

declare(strict_types=1);

namespace GSpot\Admin;

use GSpot\Security\LoginThrottle;
use GSpot\Security\SessionGuard;
use Symfony\Component\Yaml\Yaml;

class AuthManager
{
    private string $accountsPath;
    private ?array $currentUser = null;
    private LoginThrottle $loginThrottle;

    public function __construct(
        string $accountsPath,
        string $cacheDir,
        int $maxLoginAttempts = 5,
        int $loginLockoutSeconds = 900,
    ) {
        $this->accountsPath = $accountsPath;
        $this->loginThrottle = new LoginThrottle(
            rtrim($cacheDir, '/\\') . '/login-throttle.json',
            $maxLoginAttempts,
            $loginLockoutSeconds,
        );

        SessionGuard::start();

        if (isset($_SESSION['gspot_user'])) {
            $this->currentUser = $_SESSION['gspot_user'];
        }

        $this->ensureDefaultAdmin();
    }

    public function isLoginThrottled(string $username, ?string $clientIp = null): bool
    {
        return $this->loginThrottle->isLocked($this->resolveClientIp($clientIp), $username);
    }

    public function loginThrottleSecondsRemaining(string $username, ?string $clientIp = null): int
    {
        return $this->loginThrottle->secondsRemaining($this->resolveClientIp($clientIp), $username);
    }

    public function attempt(string $username, string $password, ?string $clientIp = null): bool
    {
        $ip = $this->resolveClientIp($clientIp);

        if ($this->loginThrottle->isLocked($ip, $username)) {
            return false;
        }

        $file = $this->accountsPath . '/' . $this->sanitizeFilename($username) . '.yaml';

        if (!file_exists($file)) {
            $this->loginThrottle->recordFailure($ip, $username);

            return false;
        }

        $account = Yaml::parseFile($file);
        if (!is_array($account)) {
            $this->loginThrottle->recordFailure($ip, $username);

            return false;
        }

        $hash = $account['password'] ?? '';
        if (!password_verify($password, $hash)) {
            $this->loginThrottle->recordFailure($ip, $username);

            return false;
        }

        $this->loginThrottle->clear($ip, $username);

        $this->currentUser = [
            'username' => $account['username'] ?? $username,
            'email' => $account['email'] ?? '',
            'role' => $account['role'] ?? 'admin',
        ];

        $_SESSION['gspot_user'] = $this->currentUser;
        SessionGuard::regenerate();

        return true;
    }

    public function logout(): void
    {
        $this->currentUser = null;
        unset($_SESSION['gspot_user']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }

    public function currentUser(): ?string
    {
        return $this->currentUser['username'] ?? null;
    }

    public function currentUserData(): ?array
    {
        return $this->currentUser;
    }

    public function createUser(string $username, string $password, string $email = '', string $role = 'admin'): void
    {
        if (!is_dir($this->accountsPath)) {
            mkdir($this->accountsPath, 0755, true);
        }

        $data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email' => $email,
            'role' => $role,
            'created' => date('Y-m-d H:i:s'),
        ];

        $file = $this->accountsPath . '/' . $this->sanitizeFilename($username) . '.yaml';
        file_put_contents($file, Yaml::dump($data, 4, 2));
    }

    public function listUsers(): array
    {
        $users = [];

        if (!is_dir($this->accountsPath)) {
            return $users;
        }

        $files = glob($this->accountsPath . '/*.yaml');
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (is_array($data)) {
                $users[] = [
                    'username' => $data['username'] ?? basename($file, '.yaml'),
                    'email' => $data['email'] ?? '',
                    'role' => $data['role'] ?? 'admin',
                ];
            }
        }

        return $users;
    }

    private function ensureDefaultAdmin(): void
    {
        if (!is_dir($this->accountsPath)) {
            mkdir($this->accountsPath, 0755, true);
        }

        $files = glob($this->accountsPath . '/*.yaml');
        if (empty($files)) {
            $this->createUser('admin', 'gspot-admin', '', 'admin');
        }
    }

    private function resolveClientIp(?string $clientIp): string
    {
        if ($clientIp !== null && $clientIp !== '') {
            return $clientIp;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }
}
