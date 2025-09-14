<?php

namespace App\Services;

use Aws\Route53Domains\Route53DomainsClient;
use Aws\Exception\AwsException;

class ContactUpdateService
{
    private Route53DomainsClient $route53DomainsClient;
    private array $contactInfo;
    private string $csvFilePath;

    public function __construct(Route53DomainsClient $route53DomainsClient, array $contactInfo, string $csvFilePath)
    {
        $this->route53DomainsClient = $route53DomainsClient;
        $this->contactInfo = $contactInfo;
        $this->csvFilePath = $csvFilePath;
    }

    /**
     * Load domains from CSV file
     *
     * @return array
     */
    public function loadDomainsFromCsv(): array
    {
        $domains = [];
        
        if (!file_exists($this->csvFilePath)) {
            throw new \Exception("CSV file not found: {$this->csvFilePath}");
        }

        $handle = fopen($this->csvFilePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open CSV file: {$this->csvFilePath}");
        }

        // Skip header row
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 4) {
                $domains[] = [
                    'domain_name' => trim($row[0]),
                    'update_admin' => filter_var(trim($row[1]), FILTER_VALIDATE_BOOLEAN),
                    'update_registrant' => filter_var(trim($row[2]), FILTER_VALIDATE_BOOLEAN),
                    'update_tech' => filter_var(trim($row[3]), FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        fclose($handle);
        return $domains;
    }

    /**
     * Update contacts for a domain
     *
     * @param string $domainName
     * @param array $contactTypes
     * @param bool $isDryRun
     * @return array
     */
    public function updateDomainContacts(string $domainName, array $contactTypes, bool $isDryRun = false): array
    {
        $results = [
            'domain' => $domainName,
            'success' => true,
            'message' => '',
            'updated_contacts' => [],
            'errors' => []
        ];

        try {
            if ($isDryRun) {
                $results['message'] = "DRY RUN: Would update contacts for {$domainName}";
                foreach ($contactTypes as $contactType) {
                    $results['updated_contacts'][] = $contactType;
                }
                return $results;
            }

            // Get current domain information
            $domainInfo = $this->route53DomainsClient->getDomainDetail(['DomainName' => $domainName]);

            $updateParams = ['DomainName' => $domainName];

            // Update admin contact
            if (in_array('admin', $contactTypes) && isset($this->contactInfo['admin_contact'])) {
                $updateParams['AdminContact'] = $this->contactInfo['admin_contact'];
                $results['updated_contacts'][] = 'admin';
            }

            // Update registrant contact
            if (in_array('registrant', $contactTypes) && isset($this->contactInfo['registrant_contact'])) {
                $updateParams['RegistrantContact'] = $this->contactInfo['registrant_contact'];
                $results['updated_contacts'][] = 'registrant';
            }

            // Update tech contact
            if (in_array('tech', $contactTypes) && isset($this->contactInfo['tech_contact'])) {
                $updateParams['TechContact'] = $this->contactInfo['tech_contact'];
                $results['updated_contacts'][] = 'tech';
            }

            // Perform the update
            $this->route53DomainsClient->updateDomainContact($updateParams);
            
            $contactList = implode(', ', $results['updated_contacts']);
            $results['message'] = "Successfully updated {$contactList} contact(s) for {$domainName}";

        } catch (AwsException $e) {
            $results['success'] = false;
            $results['message'] = "Failed to update contacts for {$domainName}: " . $e->getMessage();
            $results['errors'][] = $e->getMessage();
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['message'] = "Error updating contacts for {$domainName}: " . $e->getMessage();
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Process all domains from CSV
     *
     * @param array $options
     * @return array
     */
    public function processDomains(array $options): array
    {
        $isDryRun = $options['dry_run'] ?? false;
        $updateAdmin = $options['admin_contact'] ?? false;
        $updateRegistrant = $options['registrant_contact'] ?? false;
        $updateTech = $options['tech_contact'] ?? false;

        $domains = $this->loadDomainsFromCsv();
        $results = [];

        foreach ($domains as $domain) {
            $contactTypes = [];
            
            if ($updateAdmin && $domain['update_admin']) {
                $contactTypes[] = 'admin';
            }
            if ($updateRegistrant && $domain['update_registrant']) {
                $contactTypes[] = 'registrant';
            }
            if ($updateTech && $domain['update_tech']) {
                $contactTypes[] = 'tech';
            }

            if (empty($contactTypes)) {
                $results[] = [
                    'domain' => $domain['domain_name'],
                    'success' => true,
                    'skipped' => true,
                    'message' => "No contacts to update for {$domain['domain_name']}",
                    'updated_contacts' => [],
                    'errors' => []
                ];
                continue;
            }

            $result = $this->updateDomainContacts($domain['domain_name'], $contactTypes, $isDryRun);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Display contact update summary
     *
     * @param array $results
     * @param bool $isDryRun
     * @return void
     */
    public function displaySummary(array $results, bool $isDryRun = false): void
    {
        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($results as $result) {
            if ($result['skipped'] ?? false) {
                $skipped++;
            } elseif ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
        }

        echo "============================================\n";
        echo "📊 CONTACT UPDATE SUMMARY\n";
        echo "============================================\n";
        echo "Total domains processed: " . count($results) . "\n";
        echo "✅ Successful updates: {$successful}\n";
        echo "❌ Failed updates: {$failed}\n";
        echo "⏭️  Skipped domains: {$skipped}\n\n";

        if ($isDryRun) {
            echo "🔍 This was a DRY RUN - no actual changes were made.\n";
            echo "Run the script without --dry-run to perform actual updates.\n";
        }

        // Show failed domains if any
        if ($failed > 0) {
            echo "\n❌ Failed domains:\n";
            foreach ($results as $result) {
                if (!$result['success'] && !($result['skipped'] ?? false)) {
                    echo "  - {$result['domain']}: {$result['message']}\n";
                }
            }
        }

        echo "\nContact update process finished.\n";
    }
}
