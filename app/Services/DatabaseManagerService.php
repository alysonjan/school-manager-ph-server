<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DatabaseManagerService
{
    /**
     * Get all available databases (both declared and dynamic)
     */
    public function getAvailableDatabases(): array
    {
        $declaredDatabases = $this->getDeclaredDatabases();
        $dynamicDatabases = $this->getDynamicDatabases();
        
        return array_merge($declaredDatabases, $dynamicDatabases);
    }

    /**
     * Get declared databases from config
     */
    public function getDeclaredDatabases(): array
    {
        return [
            [
                'name' => 'sm_db_users_main',
                'connection' => 'users_main',
                'type' => 'declared'
            ],
            [
                'name' => 'sm_db_wlka', 
                'connection' => 'wlka',
                'type' => 'declared'
            ]
        ];
    }

    /**
     * Get dynamic databases
     */
    public function getDynamicDatabases(): array
    {
        try {
            $pattern = env('DB_PATTERN', 'sm_db_%');
            $declaredDbs = ['sm_db_users_main', 'sm_db_wlka'];
            
            $databases = DB::connection('users_main')
                ->select("
                    SELECT SCHEMA_NAME as database_name 
                    FROM INFORMATION_SCHEMA.SCHEMATA 
                    WHERE SCHEMA_NAME LIKE ? 
                    AND SCHEMA_NAME NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
                    AND SCHEMA_NAME NOT IN (?, ?)
                    ORDER BY SCHEMA_NAME
                ", [$pattern, $declaredDbs[0], $declaredDbs[1]]);

            return array_map(function ($db) {
                return [
                    'name' => $db->database_name,
                    'connection' => $this->generateConnectionName($db->database_name),
                    'type' => 'dynamic'
                ];
            }, $databases);

        } catch (\Exception $e) {
            Log::error('Failed to get dynamic databases: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Refresh database connections cache
     */
    public function refreshDatabases(): void
    {
        Cache::forget('dynamic_databases');
        
        $dynamicDatabases = $this->getDynamicDatabases();
        
        foreach ($dynamicDatabases as $database) {
            $this->registerConnection($database['name']);
        }
    }

    /**
     * Register a new database connection dynamically
     */
    public function registerConnection(string $databaseName): void
    {
        $connectionName = $this->generateConnectionName($databaseName);

        // Skip if connection already exists (declared connections)
        if (Config::has("database.connections.{$connectionName}")) {
            return;
        }

        config(["database.connections.{$connectionName}" => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'database' => $databaseName,
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]]);
    }

    /**
     * Check if a database exists
     */
    public function databaseExists(string $databaseName): bool
    {
        try {
            $result = DB::connection('users_main')
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            
            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Failed to check if database exists: {$databaseName} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get connection name for a specific database
     */
    public function getConnectionForDatabase(string $databaseName): string
    {
        // Check if it's a declared database
        $declared = $this->getDeclaredDatabases();
        foreach ($declared as $db) {
            if ($db['name'] === $databaseName) {
                return $db['connection'];
            }
        }
        
        // Otherwise, generate dynamic connection name
        return $this->generateConnectionName($databaseName);
    }

    /**
     * Generate connection name from database name
     */
    protected function generateConnectionName(string $databaseName): string
    {
        return str_replace('sm_db_', '', $databaseName);
    }

    /**
     * Get all registered connections
     */
    public function getRegisteredConnections(): array
    {
        return array_keys(Config::get('database.connections', []));
    }

    /**
     * Test database connection
     */
    public function testConnection(string $connectionName): bool
    {
        try {
            DB::connection($connectionName)->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error("Database connection test failed for: {$connectionName} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database info
     */
    public function getDatabaseInfo(string $databaseName): array
    {
        $connectionName = $this->getConnectionForDatabase($databaseName);
        
        return [
            'name' => $databaseName,
            'connection' => $connectionName,
            'type' => $this->isDeclaredDatabase($databaseName) ? 'declared' : 'dynamic',
            'connected' => $this->testConnection($connectionName)
        ];
    }

    /**
     * Check if database is declared
     */
    protected function isDeclaredDatabase(string $databaseName): bool
    {
        $declared = $this->getDeclaredDatabases();
        foreach ($declared as $db) {
            if ($db['name'] === $databaseName) {
                return true;
            }
        }
        return false;
    }
}