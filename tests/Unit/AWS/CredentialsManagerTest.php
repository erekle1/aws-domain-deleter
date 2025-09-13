<?php

namespace Tests\Unit\AWS;

use App\AWS\CredentialsManager;
use Aws\Exception\CredentialsException;
use Tests\Unit\BaseTestCase;

class CredentialsManagerTest extends BaseTestCase
{
    private CredentialsManager $credentialsManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credentialsManager = new CredentialsManager($this->getTestConfig());
    }

    public function testGetCredentialsConfigReturnsCredentialsWithValidConfig(): void
    {
        $credentials = $this->credentialsManager->getCredentialsConfig();

        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('key', $credentials);
        $this->assertArrayHasKey('secret', $credentials);
        $this->assertArrayHasKey('token', $credentials);
        $this->assertEquals('test-key', $credentials['key']);
        $this->assertEquals('test-secret', $credentials['secret']);
        $this->assertEquals('test-token', $credentials['token']);
    }

    public function testGetCredentialsConfigWithoutSessionToken(): void
    {
        $config = $this->getTestConfig();
        $config['aws_session_token'] = '';

        $credentialsManager = new CredentialsManager($config);
        $credentials = $credentialsManager->getCredentialsConfig();

        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('key', $credentials);
        $this->assertArrayHasKey('secret', $credentials);
        $this->assertArrayNotHasKey('token', $credentials);
    }

    public function testGetCredentialsConfigThrowsExceptionWithInvalidCredentials(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $credentialsManager = new CredentialsManager($config);

        $this->expectException(CredentialsException::class);
        $this->expectExceptionMessage('No valid AWS credentials found');

        $credentialsManager->getCredentialsConfig();
    }

    public function testGetCredentialsConfigReturnsNullWithInstanceProfile(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $config['use_instance_profile'] = true;

        $credentialsManager = new CredentialsManager($config);
        $credentials = $credentialsManager->getCredentialsConfig();

        $this->assertNull($credentials);
    }

    public function testValidateCredentialsReturnsTrueWithValidCredentials(): void
    {
        $this->assertTrue($this->credentialsManager->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseWithInvalidCredentials(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $credentialsManager = new CredentialsManager($config);

        $this->assertFalse($credentialsManager->validateCredentials());
    }

    public function testValidateCredentialsReturnsTrueWithInstanceProfile(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $config['use_instance_profile'] = true;

        $credentialsManager = new CredentialsManager($config);

        $this->assertTrue($credentialsManager->validateCredentials());
    }

    public function testGetCredentialSourceReturnsEnvironmentVariables(): void
    {
        $this->mockEnvironmentVariables(['AWS_ACCESS_KEY_ID' => 'env-key']);

        $source = $this->credentialsManager->getCredentialSource();

        $this->assertEquals('Environment Variables', $source);

        $this->clearEnvironmentVariables(['AWS_ACCESS_KEY_ID']);
    }

    public function testGetCredentialSourceReturnsConfigurationFile(): void
    {
        $this->clearEnvironmentVariables(['AWS_ACCESS_KEY_ID']);

        $source = $this->credentialsManager->getCredentialSource();

        $this->assertEquals('Configuration File', $source);
    }

    public function testGetCredentialSourceReturnsInstanceProfile(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $config['use_instance_profile'] = true;

        $credentialsManager = new CredentialsManager($config);
        $this->clearEnvironmentVariables(['AWS_ACCESS_KEY_ID']);

        $source = $credentialsManager->getCredentialSource();

        $this->assertEquals('Instance Profile', $source);
    }

    public function testGetCredentialSourceReturnsDefaultProviderChain(): void
    {
        $config = $this->getConfigWithoutEnvVars();
        $credentialsManager = new CredentialsManager($config);
        $this->clearEnvironmentVariables(['AWS_ACCESS_KEY_ID']);

        $source = $credentialsManager->getCredentialSource();

        $this->assertEquals('AWS Default Provider Chain', $source);
    }
}
