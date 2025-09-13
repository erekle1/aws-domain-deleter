<?php

namespace Tests\Unit\Services;

use App\Services\Route53DomainsService;
use Aws\Route53Domains\Route53DomainsClient;
use Aws\Result;
use Aws\Exception\AwsException;
use Tests\Unit\BaseTestCase;
use Mockery;
use DateTime;

class Route53DomainsServiceTest extends BaseTestCase
{
    private Route53DomainsClient $mockClient;
    private Route53DomainsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(Route53DomainsClient::class);
        $this->service = new Route53DomainsService($this->mockClient, false, false);
    }

    public function testGetDomainInfoReturnsInfoForRegisteredDomain(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('DomainName')
                   ->andReturn('example.com');
        $mockResult->shouldReceive('get')
                   ->with('StatusList')
                   ->andReturn(['ok']);
        $mockResult->shouldReceive('get')
                   ->with('ExpirationDate')
                   ->andReturn(new DateTime('2025-01-01'));
        $mockResult->shouldReceive('get')
                   ->with('AutoRenew')
                   ->andReturn(true);
        $mockResult->shouldReceive('get')
                   ->with('RegistrantContact')
                   ->andReturn(['Name' => 'Test User']);
        $mockResult->shouldReceive('get')
                   ->with('AdminContact')
                   ->andReturn(['Name' => 'Test Admin']);
        $mockResult->shouldReceive('get')
                   ->with('TechContact')
                   ->andReturn(['Name' => 'Test Tech']);

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->with(['DomainName' => 'example.com'])
                         ->andReturn($mockResult);

        $result = $this->service->getDomainInfo('example.com');

        $this->assertIsArray($result);
        $this->assertEquals('example.com', $result['domain_name']);
        $this->assertEquals(['ok'], $result['status']);
        $this->assertTrue($result['auto_renew']);
    }

    public function testGetDomainInfoReturnsNullForNonExistentDomain(): void
    {
        $awsException = new AwsException(
            'DomainNotFound',
            Mockery::mock(\Aws\CommandInterface::class),
            ['code' => 'InvalidParameterValue']
        );

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->andThrow($awsException);

        $result = $this->service->getDomainInfo('nonexistent.com');

        $this->assertNull($result);
    }

    public function testGetDomainInfoThrowsExceptionForOtherErrors(): void
    {
        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getMessage')
                     ->andReturn('AccessDenied');

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->andThrow($awsException);

        $this->expectException(AwsException::class);

        $this->service->getDomainInfo('example.com');
    }

    public function testDisableAutoRenewalSucceeds(): void
    {
        $this->mockClient->shouldReceive('disableDomainAutoRenew')
                         ->with(['DomainName' => 'example.com'])
                         ->once();

        $this->expectOutputRegex('/Disabled auto-renewal for example\.com/');

        $result = $this->service->disableAutoRenewal('example.com');

        $this->assertTrue($result);
    }

    public function testDisableAutoRenewalHandlesError(): void
    {
        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getMessage')
                     ->andReturn('Access denied');

        $this->mockClient->shouldReceive('disableDomainAutoRenew')
                         ->andThrow($awsException);

        $this->expectOutputRegex('/Warning: Could not disable auto-renewal/');

        $result = $this->service->disableAutoRenewal('example.com');

        $this->assertFalse($result);
    }

    public function testDisableAutoRenewalInDryRunMode(): void
    {
        $dryRunService = new Route53DomainsService($this->mockClient, true, false);

        $this->mockClient->shouldNotReceive('disableDomainAutoRenew');

        $this->expectOutputRegex('/\[DRY RUN\] Would disable auto-renewal/');

        $result = $dryRunService->disableAutoRenewal('example.com');

        $this->assertTrue($result);
    }

    public function testDeleteDomainRegistrationSucceeds(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('OperationId')
                   ->andReturn('op-123456789');

        // Mock array access for $result['OperationId']
        $mockResult->shouldReceive('offsetExists')
                   ->with('OperationId')
                   ->andReturn(true);

        $mockResult->shouldReceive('offsetGet')
                   ->with('OperationId')
                   ->andReturn('op-123456789');

        $this->mockClient->shouldReceive('deleteDomain')
                         ->with(['DomainName' => 'example.com'])
                         ->andReturn($mockResult);

        $this->expectOutputRegex('/PERMANENTLY DELETED domain registration/');
        $this->expectOutputRegex('/Operation ID: op-123456789/');

        $result = $this->service->deleteDomainRegistration('example.com');

        $this->assertTrue($result['success']);
        $this->assertEquals('example.com', $result['domain']);
        $this->assertEquals('op-123456789', $result['operation_id']);
    }

    public function testDeleteDomainRegistrationHandlesError(): void
    {
        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getMessage')
                     ->andReturn('Domain cannot be deleted');

        $this->mockClient->shouldReceive('deleteDomain')
                         ->andThrow($awsException);

        $this->expectOutputRegex('/Error: Could not delete domain registration/');

        $result = $this->service->deleteDomainRegistration('example.com');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['error']);
    }

    public function testDeleteDomainRegistrationInDryRunMode(): void
    {
        $dryRunService = new Route53DomainsService($this->mockClient, true, false);

        $this->mockClient->shouldNotReceive('deleteDomain');

        $this->expectOutputRegex('/\[DRY RUN\] Would PERMANENTLY DELETE domain registration/');

        $result = $dryRunService->deleteDomainRegistration('example.com');

        $this->assertTrue($result['success']);
    }

    public function testProcessDomainRegistrationWithDisableRenewalMode(): void
    {
        $service = new Route53DomainsService($this->mockClient, false, false);

        // Mock domain info
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('DomainName')
                   ->andReturn('example.com');
        $mockResult->shouldReceive('get')
                   ->with('StatusList')
                   ->andReturn(['ok']);
        $mockResult->shouldReceive('get')
                   ->with('ExpirationDate')
                   ->andReturn(new DateTime('2025-01-01'));
        $mockResult->shouldReceive('get')
                   ->with('AutoRenew')
                   ->andReturn(true);
        $mockResult->shouldReceive('get')
                   ->with('RegistrantContact')
                   ->andReturn(['Name' => 'Test User']);
        $mockResult->shouldReceive('get')
                   ->with('AdminContact')
                   ->andReturn(['Name' => 'Test Admin']);
        $mockResult->shouldReceive('get')
                   ->with('TechContact')
                   ->andReturn(['Name' => 'Test Tech']);

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->andReturn($mockResult);

        $this->mockClient->shouldReceive('disableDomainAutoRenew')
                         ->once();

        $this->expectOutputRegex('/Checking domain registration for: example\.com/');
        $this->expectOutputRegex('/Found registered domain: example\.com/');
        $this->expectOutputRegex('/Auto-renew: Enabled/');

        $result = $service->processDomainRegistration('example.com');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['skipped']);
    }

    public function testProcessDomainRegistrationWithPermanentDeletionMode(): void
    {
        $service = new Route53DomainsService($this->mockClient, false, true);

        // Mock domain info
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('DomainName')
                   ->andReturn('example.com');
        $mockResult->shouldReceive('get')
                   ->with('StatusList')
                   ->andReturn(['ok']);
        $mockResult->shouldReceive('get')
                   ->with('ExpirationDate')
                   ->andReturn(new DateTime('2025-01-01'));
        $mockResult->shouldReceive('get')
                   ->with('AutoRenew')
                   ->andReturn(true);
        $mockResult->shouldReceive('get')
                   ->with('RegistrantContact')
                   ->andReturn(['Name' => 'Test User']);
        $mockResult->shouldReceive('get')
                   ->with('AdminContact')
                   ->andReturn(['Name' => 'Test Admin']);
        $mockResult->shouldReceive('get')
                   ->with('TechContact')
                   ->andReturn(['Name' => 'Test Tech']);

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->andReturn($mockResult);

        $deleteResult = Mockery::mock(Result::class);
        $deleteResult->shouldReceive('get')
                     ->with('OperationId')
                     ->andReturn('op-123456789');

        // Mock array access for $result['OperationId']
        $deleteResult->shouldReceive('offsetExists')
                     ->with('OperationId')
                     ->andReturn(true);

        $deleteResult->shouldReceive('offsetGet')
                     ->with('OperationId')
                     ->andReturn('op-123456789');

        $this->mockClient->shouldReceive('deleteDomain')
                         ->andReturn($deleteResult);

        $this->expectOutputRegex('/PERMANENT DELETION MODE ENABLED/');

        $result = $service->processDomainRegistration('example.com');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['skipped']);
    }

    public function testProcessDomainRegistrationWithNonExistentDomain(): void
    {
        $awsException = new AwsException(
            'DomainNotFound',
            Mockery::mock(\Aws\CommandInterface::class),
            ['code' => 'InvalidParameterValue']
        );

        $this->mockClient->shouldReceive('getDomainDetail')
                         ->andThrow($awsException);

        $this->expectOutputRegex('/not registered with Route 53 Domains/');

        $result = $this->service->processDomainRegistration('nonexistent.com');

        $this->assertFalse($result['success']); // The method returns success=false for non-existent domains
        $this->assertTrue($result['skipped']);
    }

    public function testInitiateDomainTransferOutReturnsInstructions(): void
    {
        $result = $this->service->initiateDomainTransferOut('example.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('Domain transfer out must be initiated manually through AWS console', $result['message']);
        $this->assertArrayHasKey('instructions', $result);
        $this->assertIsArray($result['instructions']);
    }

    public function testInitiateDomainTransferOutInDryRunMode(): void
    {
        $dryRunService = new Route53DomainsService($this->mockClient, true, false);

        $result = $dryRunService->initiateDomainTransferOut('example.com');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('[DRY RUN]', $result['message']);
    }
}
