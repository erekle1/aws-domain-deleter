<?php

namespace Tests\Unit\Services;

use App\Services\UserInterface;
use Tests\Unit\BaseTestCase;

class UserInterfaceTest extends BaseTestCase
{
    public function testParseArgumentsWithDryRun(): void
    {
        $argv = ['delete.php', '--dry-run'];
        $options = UserInterface::parseArguments($argv);

        $this->assertTrue($options['dry_run']);
        $this->assertFalse($options['force']);
        $this->assertFalse($options['help']);
    }

    public function testParseArgumentsWithForce(): void
    {
        $argv = ['delete.php', '--force'];
        $options = UserInterface::parseArguments($argv);

        $this->assertFalse($options['dry_run']);
        $this->assertTrue($options['force']);
        $this->assertFalse($options['help']);
    }

    public function testParseArgumentsWithHelp(): void
    {
        $argv = ['delete.php', '--help'];
        $options = UserInterface::parseArguments($argv);

        $this->assertFalse($options['dry_run']);
        $this->assertFalse($options['force']);
        $this->assertTrue($options['help']);
    }

    public function testParseArgumentsWithHelpShortForm(): void
    {
        $argv = ['delete.php', '-h'];
        $options = UserInterface::parseArguments($argv);

        $this->assertTrue($options['help']);
    }

    public function testParseArgumentsWithMultipleOptions(): void
    {
        $argv = ['delete.php', '--dry-run', '--force'];
        $options = UserInterface::parseArguments($argv);

        $this->assertTrue($options['dry_run']);
        $this->assertTrue($options['force']);
        $this->assertFalse($options['help']);
    }

    public function testParseArgumentsWithNoOptions(): void
    {
        $argv = ['delete.php'];
        $options = UserInterface::parseArguments($argv);

        $this->assertFalse($options['dry_run']);
        $this->assertFalse($options['force']);
        $this->assertFalse($options['help']);
    }

    public function testDisplayHeaderOutputsCorrectContent(): void
    {
        $ui = new UserInterface(false, false);

        $this->expectOutputRegex('/🔧 AWS Route 53 Domain Deleter/');
        $this->expectOutputRegex('/=============================/');

        $ui->displayHeader();
    }

    public function testDisplayHeaderWithDryRunMode(): void
    {
        $ui = new UserInterface(true, false);

        $this->expectOutputRegex('/🔍 DRY RUN MODE/');
        $this->expectOutputRegex('/No actual deletions will be performed/');

        $ui->displayHeader();
    }

    public function testDisplayConnectionStatusOutputsCorrectContent(): void
    {
        $ui = new UserInterface(false, false);

        $this->expectOutputRegex('/🔧 Testing AWS connection/');
        $this->expectOutputRegex('/📋 Using credentials from: Test Source/');

        $ui->displayConnectionStatus('Test Source');
    }

    public function testDisplayConnectionSuccessOutputsCorrectContent(): void
    {
        $ui = new UserInterface(false, false);

        $this->expectOutputRegex('/✅ AWS connection successful/');

        $ui->displayConnectionSuccess();
    }

    public function testDisplayCancellationOutputsCorrectContent(): void
    {
        $ui = new UserInterface(false, false);

        $this->expectOutputRegex('/Operation cancelled by user/');

        $ui->displayCancellation();
    }

    public function testGetUserConfirmationReturnsTrueForDryRun(): void
    {
        $ui = new UserInterface(true, false);
        $config = $this->getTestConfig();

        $result = $ui->getUserConfirmation(5, $config);

        $this->assertTrue($result);
    }

    public function testGetUserConfirmationReturnsTrueForForce(): void
    {
        $ui = new UserInterface(false, true);
        $config = $this->getTestConfig();

        $result = $ui->getUserConfirmation(5, $config);

        $this->assertTrue($result);
    }

    public function testDisplaySummaryWithHostedZonesOnly(): void
    {
        $ui = new UserInterface(false, false);
        $results = [
            [
                'domain' => 'example.com',
                'hosted_zone_result' => ['success' => true, 'skipped' => false],
                'domain_registration_result' => null
            ],
            [
                'domain' => 'test.com',
                'hosted_zone_result' => ['success' => false, 'skipped' => false, 'message' => 'Error'],
                'domain_registration_result' => null
            ]
        ];

        $this->expectOutputRegex('/📊 EXECUTION SUMMARY/');
        $this->expectOutputRegex('/🗂️  HOSTED ZONES:/');
        $this->expectOutputRegex('/✅ Successful deletions: 1/');
        $this->expectOutputRegex('/❌ Failed deletions: 1/');

        $ui->displaySummary($results, 2);
    }

    public function testDisplaySummaryWithDomainRegistrations(): void
    {
        $ui = new UserInterface(false, false);
        $results = [
            [
                'domain' => 'example.com',
                'hosted_zone_result' => ['success' => true, 'skipped' => false],
                'domain_registration_result' => ['success' => true, 'skipped' => false]
            ]
        ];

        $this->expectOutputRegex('/🗂️  HOSTED ZONES:/');
        $this->expectOutputRegex('/🌐 DOMAIN REGISTRATIONS:/');

        $ui->displaySummary($results, 1);
    }

    public function testDisplaySummaryWithDryRunMessage(): void
    {
        $ui = new UserInterface(true, false);
        $results = [];

        $this->expectOutputRegex('/🔍 This was a DRY RUN/');
        $this->expectOutputRegex('/Run the script without --dry-run/');

        $ui->displaySummary($results, 0);
    }

    public function testDisplayHelpOutputsCorrectContent(): void
    {
        $this->expectOutputRegex('/AWS Route 53 Domain Deleter/');
        $this->expectOutputRegex('/Usage: php delete\.php \[options\]/');
        $this->expectOutputRegex('/--dry-run/');
        $this->expectOutputRegex('/--force/');
        $this->expectOutputRegex('/--help/');

        UserInterface::displayHelp();
    }

    public function testGetUserConfirmationWithPermanentDeletion(): void
    {
        $ui = new UserInterface(false, true); // Use force mode to show messages but auto-confirm
        $config = $this->getTestConfig();
        $config['delete_domain_registrations'] = true;
        $config['permanently_delete_domains'] = true;

        $this->expectOutputRegex('/PERMANENTLY DELETE domain registrations/');
        $this->expectOutputRegex('/💀 - This will completely remove domains/');

        $result = $ui->getUserConfirmation(1, $config);
        $this->assertTrue($result);
    }

    public function testGetUserConfirmationWithoutPermanentDeletion(): void
    {
        $ui = new UserInterface(false, true); // Use force mode to show messages but auto-confirm
        $config = $this->getTestConfig();
        $config['delete_domain_registrations'] = true;
        $config['permanently_delete_domains'] = false;

        $this->expectOutputRegex('/Process domain registrations \(disable auto-renewal\)/');
        $this->expectOutputRegex('/You will need to manually transfer domains/');

        $result = $ui->getUserConfirmation(1, $config);
        $this->assertTrue($result);
    }
}
