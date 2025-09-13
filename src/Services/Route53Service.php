<?php

namespace App\Services;

use Aws\Route53\Route53Client;
use Aws\Exception\AwsException;

class Route53Service
{
    private Route53Client $client;
    private bool $dryRun;

    public function __construct(Route53Client $client, bool $dryRun = false)
    {
        $this->client = $client;
        $this->dryRun = $dryRun;
    }

    /**
     * Find hosted zone for a domain
     *
     * @param string $domain
     * @return array|null
     */
    public function findHostedZone(string $domain): ?array
    {
        $domainWithDot = rtrim($domain, '.') . '.';

        $result = $this->client->listHostedZonesByName([
            'DNSName' => $domainWithDot,
            'MaxItems' => 1
        ]);

        $hostedZones = $result->get('HostedZones');

        if (empty($hostedZones) || $hostedZones[0]['Name'] !== $domainWithDot) {
            return null;
        }

        return [
            'id' => str_replace('/hostedzone/', '', $hostedZones[0]['Id']),
            'name' => $hostedZones[0]['Name'],
            'domain' => $domain,
        ];
    }

    /**
     * Delete all non-essential records from a hosted zone
     *
     * @param string $hostedZoneId
     * @return array Statistics about deleted records
     */
    public function deleteRecords(string $hostedZoneId): array
    {
        $records = $this->client->listResourceRecordSets([
            'HostedZoneId' => $hostedZoneId
        ]);

        $changeBatch = [
            'Changes' => []
        ];

        $deletedRecords = [];

        foreach ($records->get('ResourceRecordSets') as $record) {
            if ($record['Type'] !== 'NS' && $record['Type'] !== 'SOA') {
                $deletedRecords[] = [
                    'name' => $record['Name'],
                    'type' => $record['Type']
                ];

                if (!$this->dryRun) {
                    $changeBatch['Changes'][] = [
                        'Action' => 'DELETE',
                        'ResourceRecordSet' => $record
                    ];
                }
            }
        }

        if (!$this->dryRun && !empty($changeBatch['Changes'])) {
            $this->client->changeResourceRecordSets([
                'HostedZoneId' => $hostedZoneId,
                'ChangeBatch' => $changeBatch
            ]);
        }

        return [
            'deleted_count' => count($deletedRecords),
            'records' => $deletedRecords
        ];
    }

    /**
     * Delete a hosted zone
     *
     * @param string $hostedZoneId
     * @return bool
     */
    public function deleteHostedZone(string $hostedZoneId): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $this->client->deleteHostedZone([
            'Id' => $hostedZoneId
        ]);

        return true;
    }

    /**
     * Process a single domain deletion
     *
     * @param string $domain
     * @return array Result of the operation
     */
    public function processDomain(string $domain): array
    {
        try {
            echo "Processing domain: **{$domain}**\n";

            // Find hosted zone
            $hostedZone = $this->findHostedZone($domain);

            if (!$hostedZone) {
                echo "-> Hosted Zone for '{$domain}' not found. Skipping.\n";
                return [
                    'success' => false,
                    'skipped' => true,
                    'domain' => $domain,
                    'message' => 'Hosted zone not found'
                ];
            }

            echo "-> Found Hosted Zone ID: {$hostedZone['id']}\n";

            if ($this->dryRun) {
                echo "-> [DRY RUN] Would delete hosted zone: {$hostedZone['id']}\n\n";
                return [
                    'success' => true,
                    'skipped' => false,
                    'domain' => $domain,
                    'hosted_zone_id' => $hostedZone['id'],
                    'message' => 'Dry run - would be deleted'
                ];
            }

            // Delete records first
            $recordsResult = $this->deleteRecords($hostedZone['id']);

            if ($recordsResult['deleted_count'] > 0) {
                echo "--> Deleted {$recordsResult['deleted_count']} DNS records\n";
                foreach ($recordsResult['records'] as $record) {
                    echo "    • {$record['name']} ({$record['type']})\n";
                }
            } else {
                echo "--> No non-essential records to delete\n";
            }

            // Delete the hosted zone
            $this->deleteHostedZone($hostedZone['id']);

            echo "✅ Successfully deleted Hosted Zone for '{$domain}' (ID: {$hostedZone['id']})\n\n";

            return [
                'success' => true,
                'skipped' => false,
                'domain' => $domain,
                'hosted_zone_id' => $hostedZone['id'],
                'deleted_records' => $recordsResult['deleted_count'],
                'message' => 'Successfully deleted'
            ];
        } catch (AwsException $e) {
            echo "❌ Error deleting '{$domain}': " . $e->getMessage() . "\n\n";

            return [
                'success' => false,
                'skipped' => false,
                'domain' => $domain,
                'message' => $e->getMessage(),
                'error' => true
            ];
        }
    }
}
