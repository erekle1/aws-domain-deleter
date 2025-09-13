<?php

namespace App\Services;

use Aws\Route53Domains\Route53DomainsClient;
use Aws\Exception\AwsException;

class Route53DomainsService
{
    private Route53DomainsClient $client;
    private bool $dryRun;
    private bool $permanentlyDeleteDomains;

    public function __construct(Route53DomainsClient $client, bool $dryRun = false, bool $permanentlyDeleteDomains = false)
    {
        $this->client = $client;
        $this->dryRun = $dryRun;
        $this->permanentlyDeleteDomains = $permanentlyDeleteDomains;
    }

    /**
     * Check if a domain is registered in Route 53 Domains
     * 
     * @param string $domain
     * @return array|null
     */
    public function getDomainInfo(string $domain): ?array
    {
        try {
            $result = $this->client->getDomainDetail([
                'DomainName' => $domain
            ]);

            return [
                'domain_name' => $result->get('DomainName'),
                'status' => $result->get('StatusList'),
                'expiry' => $result->get('ExpirationDate'),
                'auto_renew' => $result->get('AutoRenew'),
                'registrant_contact' => $result->get('RegistrantContact'),
                'admin_contact' => $result->get('AdminContact'),
                'tech_contact' => $result->get('TechContact'),
            ];
        } catch (AwsException $e) {
            // Domain not found or access denied
            if (strpos($e->getMessage(), 'InvalidInput') !== false || 
                strpos($e->getMessage(), 'DomainNotFound') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Disable auto-renewal for a domain
     * 
     * @param string $domain
     * @return bool
     */
    public function disableAutoRenewal(string $domain): bool
    {
        if ($this->dryRun) {
            echo "    -> [DRY RUN] Would disable auto-renewal for {$domain}\n";
            return true;
        }

        try {
            $this->client->disableDomainAutoRenew([
                'DomainName' => $domain
            ]);
            echo "    -> Disabled auto-renewal for {$domain}\n";
            return true;
        } catch (AwsException $e) {
            echo "    -> Warning: Could not disable auto-renewal for {$domain}: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Delete domain registration permanently (IRREVERSIBLE!)
     * 
     * @param string $domain
     * @return array
     */
    public function deleteDomainRegistration(string $domain): array
    {
        if ($this->dryRun) {
            echo "    -> [DRY RUN] Would PERMANENTLY DELETE domain registration: {$domain}\n";
            return [
                'success' => true,
                'message' => '[DRY RUN] Would permanently delete domain registration',
                'domain' => $domain
            ];
        }

        try {
            $result = $this->client->deleteDomain([
                'DomainName' => $domain
            ]);

            echo "    -> ⚠️ PERMANENTLY DELETED domain registration: {$domain}\n";
            echo "    -> Operation ID: " . ($result['OperationId'] ?? 'N/A') . "\n";
            
            return [
                'success' => true,
                'message' => 'Domain registration permanently deleted',
                'domain' => $domain,
                'operation_id' => $result['OperationId'] ?? null
            ];
        } catch (AwsException $e) {
            echo "    -> Error: Could not delete domain registration for {$domain}: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'message' => 'Failed to delete domain registration: ' . $e->getMessage(),
                'domain' => $domain,
                'error' => true
            ];
        }
    }

    /**
     * Transfer domain to another registrar (deletion alternative)
     * Note: This is safer than permanent deletion
     * 
     * @param string $domain
     * @param string $authCode
     * @return array
     */
    public function initiateDomainTransferOut(string $domain, string $authCode = null): array
    {
        if ($this->dryRun) {
            return [
                'success' => true,
                'message' => '[DRY RUN] Would initiate transfer out for domain',
                'domain' => $domain
            ];
        }

        // Note: AWS Route 53 Domains doesn't have a direct "transfer out" API
        // This would need to be done manually through the AWS console or by
        // contacting AWS support for bulk transfers
        
        return [
            'success' => false,
            'message' => 'Domain transfer out must be initiated manually through AWS console',
            'domain' => $domain,
            'instructions' => [
                '1. Log into AWS Route 53 Domains console',
                '2. Select the domain',
                '3. Request transfer authorization code',
                '4. Initiate transfer with new registrar',
                '5. Or contact AWS Support for bulk operations'
            ]
        ];
    }

    /**
     * Process domain registration deletion (disable renewal + provide instructions)
     * 
     * @param string $domain
     * @return array
     */
    public function processDomainRegistration(string $domain): array
    {
        try {
            echo "  🔍 Checking domain registration for: {$domain}\n";

            // Check if domain is registered with Route 53
            $domainInfo = $this->getDomainInfo($domain);
            
            if (!$domainInfo) {
                echo "  -> Domain '{$domain}' is not registered with Route 53 Domains. Skipping.\n";
                return [
                    'success' => false,
                    'skipped' => true,
                    'domain' => $domain,
                    'message' => 'Not registered with Route 53 Domains'
                ];
            }

            echo "  -> Found registered domain: {$domain}\n";
            echo "  -> Status: " . implode(', ', $domainInfo['status']) . "\n";
            echo "  -> Expiry: " . ($domainInfo['expiry'] ? $domainInfo['expiry']->format('Y-m-d') : 'Unknown') . "\n";
            echo "  -> Auto-renew: " . ($domainInfo['auto_renew'] ? 'Enabled' : 'Disabled') . "\n";

            $results = [];

            // Handle domain deletion based on configuration
            if ($this->permanentlyDeleteDomains) {
                echo "  ⚠️ PERMANENT DELETION MODE ENABLED\n";
                $deletionResult = $this->deleteDomainRegistration($domain);
                $results['domain_deleted'] = $deletionResult;
                
                if ($deletionResult['success']) {
                    if ($this->dryRun) {
                        echo "  ✅ [DRY RUN] Domain registration would be PERMANENTLY DELETED: {$domain}\n\n";
                    } else {
                        echo "  ✅ Domain registration PERMANENTLY DELETED: {$domain}\n\n";
                    }
                    
                    return [
                        'success' => true,
                        'skipped' => false,
                        'domain' => $domain,
                        'domain_info' => $domainInfo,
                        'results' => $results,
                        'message' => 'Domain registration permanently deleted'
                    ];
                }
            } else {
                // Disable auto-renewal if enabled
                if ($domainInfo['auto_renew']) {
                    $renewalDisabled = $this->disableAutoRenewal($domain);
                    $results['auto_renewal_disabled'] = $renewalDisabled;
                } else {
                    echo "  -> Auto-renewal already disabled\n";
                    $results['auto_renewal_disabled'] = true;
                }

                // Provide transfer instructions
                $transferInfo = $this->initiateDomainTransferOut($domain);
                $results['transfer_info'] = $transferInfo;

                if ($this->dryRun) {
                    echo "  ✅ [DRY RUN] Domain registration processed: {$domain}\n\n";
                } else {
                    echo "  ⚠️  Domain registration processed: {$domain}\n";
                    echo "  📋 Next steps for complete removal:\n";
                    foreach ($transferInfo['instructions'] as $instruction) {
                        echo "     {$instruction}\n";
                    }
                    echo "\n";
                }
            }

            return [
                'success' => true,
                'skipped' => false,
                'domain' => $domain,
                'domain_info' => $domainInfo,
                'results' => $results,
                'message' => 'Auto-renewal disabled, manual transfer required'
            ];

        } catch (AwsException $e) {
            echo "  ❌ Error processing domain registration '{$domain}': " . $e->getMessage() . "\n\n";
            
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
