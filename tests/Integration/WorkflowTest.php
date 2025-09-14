<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class WorkflowTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scriptPath = __DIR__ . '/../../aws-domain-manager.php';
    }

    public function testScriptShowsHelpMessage(): void
    {
        $output = shell_exec("php {$this->scriptPath} --help 2>&1");

        $this->assertStringContainsString('AWS Domain Manager', $output);
        $this->assertStringContainsString('Usage: php aws-domain-manager.php [operation] [options]', $output);
        $this->assertStringContainsString('--delete-domains', $output);
        $this->assertStringContainsString('--update-contacts', $output);
        $this->assertStringContainsString('--dry-run', $output);
        $this->assertStringContainsString('--force', $output);
        $this->assertStringContainsString('--help', $output);
    }

    public function testScriptFailsWithoutCredentials(): void
    {
        // Ensure no AWS env vars are set for this test
        $env = [
            'AWS_ACCESS_KEY_ID' => '',
            'AWS_SECRET_ACCESS_KEY' => '',
            'AWS_SESSION_TOKEN' => ''
        ];

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }

        $output = shell_exec("{$envString}php {$this->scriptPath} --delete-domains --dry-run 2>&1");

        $this->assertStringContainsString('No valid AWS credentials found', $output);
    }

    public function testScriptWithDryRunShowsCorrectMode(): void
    {
        $env = [
            'AWS_ACCESS_KEY_ID' => 'test-key',
            'AWS_SECRET_ACCESS_KEY' => 'test-secret'
        ];

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }

        $output = shell_exec("{$envString}php {$this->scriptPath} --delete-domains --dry-run 2>&1");

        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('No actual deletions will be performed', $output);
    }

    public function testScriptReadsDomainsFromCSV(): void
    {
        $env = [
            'AWS_ACCESS_KEY_ID' => 'test-key',
            'AWS_SECRET_ACCESS_KEY' => 'test-secret'
        ];

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }

        $output = shell_exec("{$envString}php {$this->scriptPath} --delete-domains --dry-run 2>&1");

        $this->assertStringContainsString('Found', $output);
        $this->assertStringContainsString('domains to process', $output);
    }

    public function testConfigFileEnvironmentVariablePrecedence(): void
    {
        // Test that environment variables override config file values
        $env = [
            'AWS_ACCESS_KEY_ID' => 'env-test-key',
            'AWS_SECRET_ACCESS_KEY' => 'env-test-secret',
            'DELETE_HOSTED_ZONES' => 'false',
            'DELETE_DOMAIN_REGISTRATIONS' => 'true'
        ];

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }

        $configTestScript = __DIR__ . '/../../test_config.php';
        file_put_contents($configTestScript, "<?php
require 'vendor/autoload.php';
\$configPath = __DIR__ . '/src/config/aws_config.php';
if (file_exists(\$configPath)) {
    \$config = require \$configPath;
} else {
    \$config = [
        'aws_region' => 'eu-central-1',
        'aws_access_key_id' => '',
        'aws_secret_access_key' => '',
        'aws_session_token' => '',
        'csv_file_path' => __DIR__ . '/domains.csv',
        'use_instance_profile' => false,
        'credential_provider_timeout' => 1,
        'delete_hosted_zones' => true,
        'delete_domain_registrations' => false,
        'permanently_delete_domains' => false,
    ];
}
echo 'AWS_KEY: ' . \$config['aws_access_key_id'] . PHP_EOL;
echo 'DELETE_HOSTED_ZONES: ' . (\$config['delete_hosted_zones'] ? 'true' : 'false') . PHP_EOL;
echo 'DELETE_DOMAIN_REGISTRATIONS: ' . (\$config['delete_domain_registrations'] ? 'true' : 'false') . PHP_EOL;
");

        $output = shell_exec("{$envString}php {$configTestScript} 2>&1");

        $this->assertStringContainsString('AWS_KEY: env-test-key', $output);
        $this->assertStringContainsString('DELETE_HOSTED_ZONES: false', $output);
        $this->assertStringContainsString('DELETE_DOMAIN_REGISTRATIONS: true', $output);

        unlink($configTestScript);
    }

    public function testScriptHandlesInvalidDomainFile(): void
    {
        $env = [
            'AWS_ACCESS_KEY_ID' => 'test-key',
            'AWS_SECRET_ACCESS_KEY' => 'test-secret'
        ];

        // Create a temporary config that points to non-existent file
        $tempConfig = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempConfig, "<?php
return [
    'aws_region' => 'eu-central-1',
    'aws_access_key_id' => 'test',
    'aws_secret_access_key' => 'test',
    'csv_file_path' => '/non/existent/file.csv',
    'use_instance_profile' => false,
    'credential_provider_timeout' => 1,
    'delete_hosted_zones' => true,
    'delete_domain_registrations' => false,
    'permanently_delete_domains' => false,
];
");

        // Create a test script that uses the temp config
        $testScript = tempnam(sys_get_temp_dir(), 'test_script');
        $projectRoot = realpath(__DIR__ . '/../../');
        file_put_contents($testScript, "<?php
require '{$projectRoot}/vendor/autoload.php';
use App\\Application;
use App\\Services\\UserInterface;

\$config = require '{$tempConfig}';
\$options = ['dry_run' => true, 'force' => false, 'help' => false, 'delete_domains' => true, 'update_contacts' => false, 'admin_contact' => false, 'registrant_contact' => false, 'tech_contact' => false];
\$app = new Application(\$config, \$options);
exit(\$app->run());
");

        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }

        $output = shell_exec("{$envString}php {$testScript} 2>&1");

        $this->assertStringContainsString('CSV file', $output);
        $this->assertStringContainsString('not found', $output);

        unlink($tempConfig);
        unlink($testScript);
    }
}
