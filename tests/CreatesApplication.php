<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use PDO;
use PDOException;

trait CreatesApplication
{
    protected static ?string $isolatedTestingDatabase = null;

    protected static bool $isolatedTestingDatabasePrepared = false;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $this->prepareIsolatedTestingDatabase();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function prepareIsolatedTestingDatabase(): void
    {
        if ($this->readEnvironmentValue('APP_ENV') !== 'testing') {
            return;
        }

        $baseDatabase = (string) $this->readEnvironmentValue('DB_DATABASE', 'qits_app_testing');

        if (! str_starts_with($baseDatabase, 'qits_app_testing')) {
            throw new PDOException('Testing database base name must start with qits_app_testing.');
        }

        if (self::$isolatedTestingDatabase === null) {
            $token = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $this->readEnvironmentValue('TEST_TOKEN', (string) getmypid()));
            self::$isolatedTestingDatabase = $baseDatabase.'_'.$token;
        }

        $this->writeEnvironmentValue('DB_DATABASE', self::$isolatedTestingDatabase);
        $this->writeEnvironmentValue('LOG_CHANNEL', (string) $this->readEnvironmentValue('LOG_CHANNEL', 'stderr'));

        if (self::$isolatedTestingDatabasePrepared) {
            return;
        }

        $host = (string) $this->readEnvironmentValue('DB_HOST', '127.0.0.1');
        $port = (string) $this->readEnvironmentValue('DB_PORT', '3306');
        $username = (string) $this->readEnvironmentValue('DB_USERNAME', 'root');
        $password = (string) $this->readEnvironmentValue('DB_PASSWORD', '');
        $database = $this->quoteDatabaseName(self::$isolatedTestingDatabase);

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $pdo->exec("DROP DATABASE IF EXISTS {$database}");
        $pdo->exec("CREATE DATABASE {$database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        self::$isolatedTestingDatabasePrepared = true;
    }

    protected function readEnvironmentValue(string $key, ?string $default = null): ?string
    {
        return $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    protected function writeEnvironmentValue(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    protected function quoteDatabaseName(string $databaseName): string
    {
        return '`'.str_replace('`', '``', $databaseName).'`';
    }
}
