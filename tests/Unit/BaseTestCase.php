<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;

abstract class BaseTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Get test config array
     */
    protected function getTestConfig(): array
    {
        return [
            'aws_region' => 'eu-central-1',
            'aws_access_key_id' => 'test-key',
            'aws_secret_access_key' => 'test-secret',
            'aws_session_token' => 'test-token',
            'csv_file_path' => __DIR__ . '/../Fixtures/test-domains.csv',
            'use_instance_profile' => false,
            'credential_provider_timeout' => 1,
            'delete_hosted_zones' => true,
            'delete_domain_registrations' => false,
            'permanently_delete_domains' => false,
        ];
    }

    /**
     * Get test config with environment variables cleared
     */
    protected function getConfigWithoutEnvVars(): array
    {
        return [
            'aws_region' => 'eu-central-1',
            'aws_access_key_id' => 'YOUR_AWS_ACCESS_KEY_ID',
            'aws_secret_access_key' => 'YOUR_AWS_SECRET_ACCESS_KEY',
            'aws_session_token' => 'YOUR_AWS_SESSION_TOKEN',
            'csv_file_path' => __DIR__ . '/../Fixtures/test-domains.csv',
            'use_instance_profile' => false,
            'credential_provider_timeout' => 1,
            'delete_hosted_zones' => true,
            'delete_domain_registrations' => false,
            'permanently_delete_domains' => false,
        ];
    }

    /**
     * Mock environment variables
     */
    protected function mockEnvironmentVariables(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Clear environment variables
     */
    protected function clearEnvironmentVariables(array $keys): void
    {
        foreach ($keys as $key) {
            unset($_ENV[$key]);
            putenv($key);
        }
    }
}
