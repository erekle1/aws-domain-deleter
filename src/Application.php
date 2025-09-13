<?php

namespace App;

use App\AWS\ClientFactory;
use App\Services\Route53Service;
use App\Services\Route53DomainsService;
use App\Services\DomainManager;
use App\Services\UserInterface;
use Aws\Exception\AwsException;

class Application
{
    private array $config;
    private array $options;
    private UserInterface $ui;
    private ClientFactory $clientFactory;
    private DomainManager $domainManager;

    public function __construct(array $config, array $options)
    {
        $this->config = $config;
        $this->options = $options;
        $this->ui = new UserInterface($options['dry_run'], $options['force']);
        $this->clientFactory = new ClientFactory($config);
        $this->domainManager = new DomainManager($config['csv_file_path']);
    }

    /**
     * Run the application
     * 
     * @return int Exit code
     */
    public function run(): int
    {
        try {
            // Display header
            $this->ui->displayHeader();

            // Show help if requested
            if ($this->options['help']) {
                UserInterface::displayHelp();
                return 0;
            }

            // Test AWS connection
            $this->testAwsConnection();

            // Load and validate domains
            $domains = $this->loadDomains();

            // Get user confirmation
            if (!$this->getUserConfirmation($domains)) {
                $this->ui->displayCancellation();
                return 0;
            }

            // Process domains
            $results = $this->processDomains($domains);

            // Display summary
            $this->ui->displaySummary($results, count($domains));

            return 0;

        } catch (\Exception $e) {
            echo "❌ Fatal error: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Test AWS connection
     * 
     * @throws AwsException
     */
    private function testAwsConnection(): void
    {
        $this->ui->displayConnectionStatus($this->clientFactory->getCredentialSource());

        try {
            $this->clientFactory->testConnection();
            $this->ui->displayConnectionSuccess();
        } catch (AwsException $e) {
            throw new AwsException("AWS connection failed: " . $e->getMessage(), $e->getCommand());
        }
    }

    /**
     * Load and validate domains
     * 
     * @return array
     * @throws \Exception
     */
    private function loadDomains(): array
    {
        $allDomains = $this->domainManager->loadDomains();
        $validDomains = $this->domainManager->validateDomains($allDomains);
        
        if (empty($validDomains)) {
            throw new \Exception("No valid domains found to process");
        }

        $this->domainManager->displayDomains($validDomains);
        
        return $validDomains;
    }

    /**
     * Get user confirmation
     * 
     * @param array $domains
     * @return bool
     */
    private function getUserConfirmation(array $domains): bool
    {
        return $this->ui->getUserConfirmation(count($domains), $this->config);
    }

    /**
     * Process all domains
     * 
     * @param array $domains
     * @return array
     */
    private function processDomains(array $domains): array
    {
        $results = [];
        
        // Create services based on configuration
        $route53Service = null;
        $domainsService = null;
        
        if ($this->config['delete_hosted_zones'] ?? true) {
            $route53Client = $this->clientFactory->createRoute53Client();
            $route53Service = new Route53Service($route53Client, $this->options['dry_run']);
        }
        
        if ($this->config['delete_domain_registrations'] ?? false) {
            $domainsClient = $this->clientFactory->createRoute53DomainsClient();
            $permanentlyDeleteDomains = $this->config['permanently_delete_domains'] ?? false;
            $domainsService = new Route53DomainsService($domainsClient, $this->options['dry_run'], $permanentlyDeleteDomains);
        }

        foreach ($domains as $domain) {
            $domainResults = [
                'domain' => $domain,
                'hosted_zone_result' => null,
                'domain_registration_result' => null
            ];

            // Process hosted zone deletion
            if ($route53Service) {
                echo "🗂️  Processing hosted zone for: {$domain}\n";
                $domainResults['hosted_zone_result'] = $route53Service->processDomain($domain);
            }

            // Process domain registration deletion
            if ($domainsService) {
                echo "🌐 Processing domain registration for: {$domain}\n";
                $domainResults['domain_registration_result'] = $domainsService->processDomainRegistration($domain);
            }

            $results[] = $domainResults;
        }

        return $results;
    }
}
