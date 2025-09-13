<?php

namespace App\AWS;

use Aws\Route53\Route53Client;
use Aws\Route53Domains\Route53DomainsClient;
use Aws\Exception\AwsException;
use App\AWS\CredentialsManager;

class ClientFactory
{
    private CredentialsManager $credentialsManager;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->credentialsManager = new CredentialsManager($config);
    }

    /**
     * Create a properly configured Route53 client
     * 
     * @return Route53Client
     * @throws AwsException
     */
    public function createRoute53Client(): Route53Client
    {
        $clientConfig = [
            'version' => 'latest',
            'region' => $this->config['aws_region'] ?? 'eu-central-1',
            'http' => [
                'timeout' => $this->config['credential_provider_timeout'] ?? 1,
                'connect_timeout' => $this->config['credential_provider_timeout'] ?? 1,
            ],
        ];

        // Add credentials if available
        $credentials = $this->credentialsManager->getCredentialsConfig();
        if ($credentials) {
            $clientConfig['credentials'] = $credentials;
        }

        // Disable instance profile provider if not explicitly enabled
        if (!($this->config['use_instance_profile'] ?? false)) {
            $clientConfig['credentials_provider'] = false;
        }

        return new Route53Client($clientConfig);
    }

    /**
     * Create a properly configured Route53Domains client
     * Note: Route53Domains service is only available in us-east-1
     * 
     * @return Route53DomainsClient
     * @throws AwsException
     */
    public function createRoute53DomainsClient(): Route53DomainsClient
    {
        $clientConfig = [
            'version' => 'latest',
            'region' => 'us-east-1', // Route53Domains is only available in us-east-1
            'http' => [
                'timeout' => $this->config['credential_provider_timeout'] ?? 1,
                'connect_timeout' => $this->config['credential_provider_timeout'] ?? 1,
            ],
        ];

        // Add credentials if available
        $credentials = $this->credentialsManager->getCredentialsConfig();
        if ($credentials) {
            $clientConfig['credentials'] = $credentials;
        }

        // Disable instance profile provider if not explicitly enabled
        if (!($this->config['use_instance_profile'] ?? false)) {
            $clientConfig['credentials_provider'] = false;
        }

        return new Route53DomainsClient($clientConfig);
    }

    /**
     * Test AWS connection
     * 
     * @return bool
     * @throws AwsException
     */
    public function testConnection(): bool
    {
        $client = $this->createRoute53Client();
        
        try {
            $client->listHostedZones(['MaxItems' => 1]);
            return true;
        } catch (AwsException $e) {
            throw $e;
        }
    }

    /**
     * Get credential source for display
     * 
     * @return string
     */
    public function getCredentialSource(): string
    {
        return $this->credentialsManager->getCredentialSource();
    }
}
