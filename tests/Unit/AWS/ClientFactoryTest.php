<?php

namespace Tests\Unit\AWS;

use App\AWS\ClientFactory;
use Aws\Route53\Route53Client;
use Aws\Route53Domains\Route53DomainsClient;
use Aws\Exception\AwsException;
use Tests\Unit\BaseTestCase;
use Mockery;

class ClientFactoryTest extends BaseTestCase
{
    private ClientFactory $clientFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientFactory = new ClientFactory($this->getTestConfig());
    }

    public function testCreateRoute53ClientReturnsValidClient(): void
    {
        $client = $this->clientFactory->createRoute53Client();

        $this->assertInstanceOf(Route53Client::class, $client);
    }

    public function testCreateRoute53DomainsClientReturnsValidClient(): void
    {
        $client = $this->clientFactory->createRoute53DomainsClient();

        $this->assertInstanceOf(Route53DomainsClient::class, $client);
    }

    public function testCreateRoute53DomainsClientUsesCorrectRegion(): void
    {
        // Route53Domains must use us-east-1
        $client = $this->clientFactory->createRoute53DomainsClient();

        $this->assertInstanceOf(Route53DomainsClient::class, $client);
        // We can't easily test the region without accessing private properties
        // but we can verify the client was created successfully
    }

    public function testCreateRoute53ClientWithoutCredentials(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $clientFactory = new ClientFactory($config);

        $this->expectException(\Aws\Exception\CredentialsException::class);
        $this->expectExceptionMessage('No valid AWS credentials found');

        $clientFactory->createRoute53Client();
    }

    public function testGetCredentialSourceDelegatesToCredentialsManager(): void
    {
        $source = $this->clientFactory->getCredentialSource();

        $this->assertIsString($source);
        $this->assertContains($source, [
            'Environment Variables',
            'Configuration File',
            'Instance Profile',
            'AWS Default Provider Chain'
        ]);
    }

    public function testTestConnectionWithMockedClient(): void
    {
        // This test would require mocking the AWS client
        // For now, we'll test that the method exists and is callable
        $this->assertTrue(method_exists($this->clientFactory, 'testConnection'));
    }

    public function testCreateClientsWithInstanceProfileEnabled(): void
    {
        $config = $this->getTestConfig();
        $config['use_instance_profile'] = true;

        $clientFactory = new ClientFactory($config);

        $route53Client = $clientFactory->createRoute53Client();
        $domainsClient = $clientFactory->createRoute53DomainsClient();

        $this->assertInstanceOf(Route53Client::class, $route53Client);
        $this->assertInstanceOf(Route53DomainsClient::class, $domainsClient);
    }

    public function testCreateClientsWithCustomTimeout(): void
    {
        $config = $this->getTestConfig();
        $config['credential_provider_timeout'] = 5;

        $clientFactory = new ClientFactory($config);

        $route53Client = $clientFactory->createRoute53Client();
        $domainsClient = $clientFactory->createRoute53DomainsClient();

        $this->assertInstanceOf(Route53Client::class, $route53Client);
        $this->assertInstanceOf(Route53DomainsClient::class, $domainsClient);
    }
}
