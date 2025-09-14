<?php

namespace App;

use App\AWS\ClientFactory;
use App\Services\Route53Service;
use App\Services\Route53DomainsService;
use App\Services\DomainManager;
use App\Services\UserInterface;
use App\Services\ContactUpdateService;
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

            // Check which operation to perform
            if (isset($this->options['update_contacts']) && $this->options['update_contacts']) {
                return $this->runContactUpdate();
            } elseif (isset($this->options['delete_domains']) && $this->options['delete_domains']) {
                return $this->runDomainDeletion();
            } else {
                // No operation specified, show help
                echo "❌ No operation specified. Please choose an operation:\n\n";
                UserInterface::displayHelp();
                return 1;
            }

        } catch (\Exception $e) {
            echo "❌ Fatal error: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Run domain deletion operation
     *
     * @return int Exit code
     */
    private function runDomainDeletion(): int
    {
        try {
            echo "🗑️  Domain Deletion Mode\n";
            echo "======================\n\n";

            // Load and validate domains first (before AWS connection)
            $domains = $this->loadDomains();

            // Test AWS connection
            $this->testAwsConnection();

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
            echo "❌ Domain deletion error: " . $e->getMessage() . "\n";
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

    /**
     * Run contact update operation
     *
     * @return int Exit code
     */
    private function runContactUpdate(): int
    {
        try {
            echo "📞 Contact Update Mode\n";
            echo "====================\n\n";

            // Load contact information
            $contactInfo = $this->loadContactInfo();

            // Test AWS connection
            $this->testAwsConnection();

            // Create contact update service
            $domainsClient = $this->clientFactory->createRoute53DomainsClient();
            $contactService = new ContactUpdateService($domainsClient, $contactInfo, $this->config['csv_file_path']);

            // Get user confirmation for contact updates
            if (!$this->getContactUpdateConfirmation()) {
                $this->ui->displayCancellation();
                return 0;
            }

            // Process contact updates
            $results = $contactService->processDomains($this->options);

            // Display summary
            $contactService->displaySummary($results, $this->options['dry_run']);

            return 0;
        } catch (\Exception $e) {
            echo "❌ Contact update error: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Load contact information from JSON file
     *
     * @return array
     * @throws \Exception
     */
    private function loadContactInfo(): array
    {
        $contactFile = dirname(__DIR__) . '/contacts.json';
        
        if (!file_exists($contactFile)) {
            throw new \Exception("Contact information file not found: contacts.json. Please create contacts.json in the project root.");
        }

        $contactData = file_get_contents($contactFile);
        if ($contactData === false) {
            throw new \Exception("Cannot read contact information file: contacts.json");
        }

        $contactInfo = json_decode($contactData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in contact information file: " . json_last_error_msg());
        }

        return $contactInfo;
    }

    /**
     * Get user confirmation for contact updates
     *
     * @return bool
     */
    private function getContactUpdateConfirmation(): bool
    {
        $updateTypes = [];
        if ($this->options['admin_contact']) {
            $updateTypes[] = 'admin';
        }
        if ($this->options['registrant_contact']) {
            $updateTypes[] = 'registrant';
        }
        if ($this->options['tech_contact']) {
            $updateTypes[] = 'tech';
        }

        if (empty($updateTypes)) {
            echo "⚠️  No contact types specified for update.\n";
            echo "Use --admin-contact, --registrant-contact, or --tech-contact options.\n";
            return false;
        }

        $contactList = implode(', ', $updateTypes);
        echo "⚠️  This will update {$contactList} contact(s) for domains listed in the CSV file.\n";

        if ($this->options['force'] || $this->options['dry_run']) {
            return true;
        }

        echo "\nAre you sure you want to continue? (yes/no): ";

        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        return trim(strtolower($line)) === 'yes';
    }
}
