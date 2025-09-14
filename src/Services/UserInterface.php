<?php

namespace App\Services;

class UserInterface
{
    private bool $isDryRun;
    private bool $isForced;

    public function __construct(bool $isDryRun = false, bool $isForced = false)
    {
        $this->isDryRun = $isDryRun;
        $this->isForced = $isForced;
    }

    /**
     * Display script header
     *
     * @return void
     */
    public function displayHeader(): void
    {
        echo "🔧 AWS Route 53 Domain Deleter\n";
        echo "=============================\n\n";

        if ($this->isDryRun) {
            echo "🔍 DRY RUN MODE - No actual deletions will be performed\n";
            echo "============================================\n\n";
        }
    }

    /**
     * Display AWS connection status
     *
     * @param string $credentialSource
     * @return void
     */
    public function displayConnectionStatus(string $credentialSource): void
    {
        echo "🔧 Testing AWS connection...\n";
        echo "📋 Using credentials from: {$credentialSource}\n";
    }

    /**
     * Display connection success
     *
     * @return void
     */
    public function displayConnectionSuccess(): void
    {
        echo "✅ AWS connection successful\n\n";
    }

    /**
     * Get user confirmation for deletion
     *
     * @param int $domainCount
     * @param array $config
     * @return bool
     */
    public function getUserConfirmation(int $domainCount, array $config = []): bool
    {
        $deleteHostedZones = $config['delete_hosted_zones'] ?? true;
        $deleteDomainRegistrations = $config['delete_domain_registrations'] ?? false;
        $permanentlyDeleteDomains = $config['permanently_delete_domains'] ?? false;

        echo "⚠️  WARNING: This will process {$domainCount} domains:\n";

        if ($deleteHostedZones) {
            echo "   🗂️  - Delete hosted zones from AWS Route 53\n";
        }

        if ($deleteDomainRegistrations) {
            if ($permanentlyDeleteDomains) {
                echo "   🌐 - PERMANENTLY DELETE domain registrations (IRREVERSIBLE!)\n";
                echo "   💀 - This will completely remove domains - NO REFUNDS!\n";
                echo "   💀 - Domains will be released back to public registry!\n";
            } else {
                echo "   🌐 - Process domain registrations (disable auto-renewal)\n";
                echo "   ⚠️  - You will need to manually transfer domains out to fully delete them\n";
            }
        }

        if ($this->isForced || $this->isDryRun) {
            return true;
        }

        echo "\nThis action cannot be undone. Are you sure you want to continue? (yes/no): ";

        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        return trim(strtolower($line)) === 'yes';
    }

    /**
     * Display cancellation message
     *
     * @return void
     */
    public function displayCancellation(): void
    {
        echo "Operation cancelled by user.\n";
    }

    /**
     * Display execution summary
     *
     * @param array $results
     * @param int $totalDomains
     * @return void
     */
    public function displaySummary(array $results, int $totalDomains): void
    {
        $hostedZoneStats = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        $domainRegistrationStats = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($results as $domainResult) {
            // Count hosted zone results
            if ($hostedZoneResult = $domainResult['hosted_zone_result']) {
                if ($hostedZoneResult['success'] && !$hostedZoneResult['skipped']) {
                    $hostedZoneStats['successful']++;
                } elseif (!$hostedZoneResult['success'] && !$hostedZoneResult['skipped']) {
                    $hostedZoneStats['failed']++;
                } else {
                    $hostedZoneStats['skipped']++;
                }
            }

            // Count domain registration results
            if ($domainRegistrationResult = $domainResult['domain_registration_result']) {
                if ($domainRegistrationResult['success'] && !$domainRegistrationResult['skipped']) {
                    $domainRegistrationStats['successful']++;
                } elseif (!$domainRegistrationResult['success'] && !$domainRegistrationResult['skipped']) {
                    $domainRegistrationStats['failed']++;
                } else {
                    $domainRegistrationStats['skipped']++;
                }
            }
        }

        echo "============================================\n";
        echo "📊 EXECUTION SUMMARY\n";
        echo "============================================\n";
        echo "Total domains processed: {$totalDomains}\n\n";

        // Hosted zones summary
        if ($hostedZoneStats['successful'] + $hostedZoneStats['failed'] + $hostedZoneStats['skipped'] > 0) {
            echo "🗂️  HOSTED ZONES:\n";
            echo "   ✅ Successful deletions: {$hostedZoneStats['successful']}\n";
            echo "   ❌ Failed deletions: {$hostedZoneStats['failed']}\n";
            echo "   ⏭️  Skipped domains: {$hostedZoneStats['skipped']}\n\n";
        }

        // Domain registrations summary
        if ($domainRegistrationStats['successful'] + $domainRegistrationStats['failed'] + $domainRegistrationStats['skipped'] > 0) {
            echo "🌐 DOMAIN REGISTRATIONS:\n";
            echo "   ✅ Successfully processed: {$domainRegistrationStats['successful']}\n";
            echo "   ❌ Failed to process: {$domainRegistrationStats['failed']}\n";
            echo "   ⏭️  Skipped domains: {$domainRegistrationStats['skipped']}\n\n";
        }

        if ($this->isDryRun) {
            echo "🔍 This was a DRY RUN - no actual changes were made.\n";
            echo "Run the script without --dry-run to perform actual deletions.\n";
        }

        // Show failed domains if any
        $hasFailures = false;
        foreach ($results as $domainResult) {
            $hostedZoneResult = $domainResult['hosted_zone_result'];
            $domainRegistrationResult = $domainResult['domain_registration_result'];

            if (
                ($hostedZoneResult && !$hostedZoneResult['success'] && !$hostedZoneResult['skipped']) ||
                ($domainRegistrationResult && !$domainRegistrationResult['success'] && !$domainRegistrationResult['skipped'])
            ) {
                if (!$hasFailures) {
                    echo "\n❌ Failed domains:\n";
                    $hasFailures = true;
                }

                echo "  - {$domainResult['domain']}:\n";
                if ($hostedZoneResult && !$hostedZoneResult['success'] && !$hostedZoneResult['skipped']) {
                    echo "    🗂️  Hosted Zone: {$hostedZoneResult['message']}\n";
                }
                if ($domainRegistrationResult && !$domainRegistrationResult['success'] && !$domainRegistrationResult['skipped']) {
                    echo "    🌐 Domain Registration: {$domainRegistrationResult['message']}\n";
                }
            }
        }

        echo "\nScript finished.\n";
    }

    /**
     * Parse command line arguments
     *
     * @param array $argv
     * @return array
     */
    public static function parseArguments(array $argv): array
    {
        return [
            'dry_run' => in_array('--dry-run', $argv),
            'force' => in_array('--force', $argv),
            'help' => in_array('--help', $argv) || in_array('-h', $argv),
            'delete_domains' => in_array('--delete-domains', $argv),
            'update_contacts' => in_array('--update-contacts', $argv),
            'admin_contact' => in_array('--admin-contact', $argv),
            'registrant_contact' => in_array('--registrant-contact', $argv),
            'tech_contact' => in_array('--tech-contact', $argv),
        ];
    }

    /**
     * Display help message
     *
     * @return void
     */
    public static function displayHelp(): void
    {
        echo "AWS Domain Manager\n";
        echo "==================\n\n";
        echo "Usage: php aws-domain-manager.php [operation] [options]\n\n";
        echo "Operations:\n";
        echo "  --delete-domains     Delete domain hosted zones and registrations\n";
        echo "  --update-contacts    Update domain contact information\n\n";
        echo "Common Options:\n";
        echo "  --dry-run           Preview actions without making changes\n";
        echo "  --force             Skip confirmation prompt (dangerous!)\n";
        echo "  --help, -h          Show this help message\n\n";
        echo "Contact Update Options:\n";
        echo "  --admin-contact       Update admin contact for domains\n";
        echo "  --registrant-contact  Update registrant contact for domains\n";
        echo "  --tech-contact        Update technical contact for domains\n\n";
        echo "Examples:\n";
        echo "  # Domain Deletion\n";
        echo "  php aws-domain-manager.php --delete-domains --dry-run\n";
        echo "  php aws-domain-manager.php --delete-domains\n";
        echo "  php aws-domain-manager.php --delete-domains --force\n\n";
        echo "  # Contact Updates\n";
        echo "  php aws-domain-manager.php --update-contacts --admin-contact --dry-run\n";
        echo "  php aws-domain-manager.php --update-contacts --admin-contact --tech-contact\n";
        echo "  php aws-domain-manager.php --update-contacts --admin-contact --force\n\n";
        echo "For more information, see README.md\n";
    }
}
