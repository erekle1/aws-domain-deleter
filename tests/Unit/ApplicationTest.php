<?php

namespace Tests\Unit;

use App\Application;
use App\AWS\ClientFactory;
use App\Services\DomainManager;
use App\Services\UserInterface;
use Tests\Unit\BaseTestCase;
use Mockery;

class ApplicationTest extends BaseTestCase
{
    private Application $application;
    private array $testOptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testOptions = [
            'dry_run' => false,
            'force' => false,
            'help' => false
        ];
        $this->application = new Application($this->getTestConfig(), $this->testOptions);
    }

    public function testRunWithHelpOption(): void
    {
        $options = ['dry_run' => false, 'force' => false, 'help' => true];
        $app = new Application($this->getTestConfig(), $options);

        $this->expectOutputRegex('/AWS Route 53 Domain Deleter/');
        $this->expectOutputRegex('/Usage: php delete\.php \[options\]/');

        $exitCode = $app->run();

        $this->assertEquals(0, $exitCode);
    }

    public function testRunWithInvalidCredentials(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Fatal error:/');
        $this->expectOutputRegex('/No valid AWS credentials found/');

        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testRunWithEmptyDomainFile(): void
    {
        $config = $this->getTestConfig();
        
        // Create an empty CSV file
        $emptyFile = tempnam(sys_get_temp_dir(), 'empty_domains');
        file_put_contents($emptyFile, '');
        $config['csv_file_path'] = $emptyFile;
        
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Fatal error:/');
        $this->expectOutputRegex('/CSV file is empty/');

        try {
            $exitCode = $app->run();
            $this->assertEquals(1, $exitCode);
        } finally {
            unlink($emptyFile);
        }
    }

    public function testRunWithNonExistentDomainFile(): void
    {
        $config = $this->getTestConfig();
        $config['csv_file_path'] = '/non/existent/file.csv';
        
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Fatal error:/');
        $this->expectOutputRegex('/CSV file.*not found/');

        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testRunInDryRunMode(): void
    {
        $config = $this->getTestConfig();
        $options = array_merge($this->testOptions, ['dry_run' => true]);
        
        $app = new Application($config, $options);

        $this->expectOutputRegex('/DRY RUN MODE/');
        $this->expectOutputRegex('/Testing AWS connection/');

        // This will fail due to test credentials, but we're testing the flow
        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testRunWithForceOption(): void
    {
        $config = $this->getTestConfig();
        $options = array_merge($this->testOptions, ['force' => true]);
        
        $app = new Application($config, $options);

        $this->expectOutputRegex('/Testing AWS connection/');

        // This will fail due to test credentials, but we're testing the flow
        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testApplicationProcessesConfigurationCorrectly(): void
    {
        $config = $this->getTestConfig();
        $config['delete_hosted_zones'] = false;
        $config['delete_domain_registrations'] = true;
        $config['permanently_delete_domains'] = true;
        
        $app = new Application($config, $this->testOptions);

        // Test that the application is created without errors
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testApplicationHandlesException(): void
    {
        // Create invalid config to trigger exception
        $config = [];
        
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Fatal error:/');

        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testApplicationWithValidDomainsButInvalidCredentials(): void
    {
        $config = $this->getTestConfig();
        // Reset credentials to invalid values
        $config['aws_access_key_id'] = 'invalid';
        $config['aws_secret_access_key'] = 'invalid';
        
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Testing AWS connection/');
        $this->expectOutputRegex('/AWS connection failed/');

        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }

    public function testApplicationShowsDomainsBeforeProcessing(): void
    {
        $config = $this->getTestConfig();
        $app = new Application($config, $this->testOptions);

        $this->expectOutputRegex('/Found \d+ valid domains to process/');

        // Will fail on AWS connection but should show domains first
        $exitCode = $app->run();

        $this->assertEquals(1, $exitCode);
    }
}
