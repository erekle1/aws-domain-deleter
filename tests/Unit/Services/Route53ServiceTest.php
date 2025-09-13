<?php

namespace Tests\Unit\Services;

use App\Services\Route53Service;
use Aws\Route53\Route53Client;
use Aws\Result;
use Aws\Exception\AwsException;
use Tests\Unit\BaseTestCase;
use Mockery;

class Route53ServiceTest extends BaseTestCase
{
    private Route53Client $mockClient;
    private Route53Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(Route53Client::class);
        $this->service = new Route53Service($this->mockClient, false);
    }

    public function testFindHostedZoneReturnsZoneForValidDomain(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('HostedZones')
                   ->andReturn([
                       [
                           'Id' => '/hostedzone/Z123456789',
                           'Name' => 'example.com.'
                       ]
                   ]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->with([
                             'DNSName' => 'example.com.',
                             'MaxItems' => 1
                         ])
                         ->andReturn($mockResult);

        $result = $this->service->findHostedZone('example.com');

        $this->assertIsArray($result);
        $this->assertEquals('Z123456789', $result['id']);
        $this->assertEquals('example.com.', $result['name']);
        $this->assertEquals('example.com', $result['domain']);
    }

    public function testFindHostedZoneReturnsNullForNonExistentDomain(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('HostedZones')
                   ->andReturn([]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andReturn($mockResult);

        $result = $this->service->findHostedZone('nonexistent.com');

        $this->assertNull($result);
    }

    public function testFindHostedZoneReturnsNullForMismatchedDomain(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('HostedZones')
                   ->andReturn([
                       [
                           'Id' => '/hostedzone/Z123456789',
                           'Name' => 'different.com.'
                       ]
                   ]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andReturn($mockResult);

        $result = $this->service->findHostedZone('example.com');

        $this->assertNull($result);
    }

    public function testDeleteRecordsWithNonEssentialRecords(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('ResourceRecordSets')
                   ->andReturn([
                       [
                           'Name' => 'example.com.',
                           'Type' => 'NS'
                       ],
                       [
                           'Name' => 'example.com.',
                           'Type' => 'SOA'
                       ],
                       [
                           'Name' => 'www.example.com.',
                           'Type' => 'A'
                       ],
                       [
                           'Name' => 'mail.example.com.',
                           'Type' => 'MX'
                       ]
                   ]);

        $this->mockClient->shouldReceive('listResourceRecordSets')
                         ->with(['HostedZoneId' => 'Z123456789'])
                         ->andReturn($mockResult);

        $this->mockClient->shouldReceive('changeResourceRecordSets')
                         ->with(Mockery::on(function ($args) {
                             return isset($args['HostedZoneId']) &&
                                    $args['HostedZoneId'] === 'Z123456789' &&
                                    isset($args['ChangeBatch']['Changes']) &&
                                    count($args['ChangeBatch']['Changes']) === 2;
                         }))
                         ->once();

        $result = $this->service->deleteRecords('Z123456789');

        $this->assertEquals(2, $result['deleted_count']);
        $this->assertCount(2, $result['records']);
    }

    public function testDeleteRecordsWithNoNonEssentialRecords(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('ResourceRecordSets')
                   ->andReturn([
                       [
                           'Name' => 'example.com.',
                           'Type' => 'NS'
                       ],
                       [
                           'Name' => 'example.com.',
                           'Type' => 'SOA'
                       ]
                   ]);

        $this->mockClient->shouldReceive('listResourceRecordSets')
                         ->andReturn($mockResult);

        $this->mockClient->shouldNotReceive('changeResourceRecordSets');

        $result = $this->service->deleteRecords('Z123456789');

        $this->assertEquals(0, $result['deleted_count']);
        $this->assertEmpty($result['records']);
    }

    public function testDeleteHostedZoneReturnsTrueOnSuccess(): void
    {
        $this->mockClient->shouldReceive('deleteHostedZone')
                         ->with(['Id' => 'Z123456789'])
                         ->once();

        $result = $this->service->deleteHostedZone('Z123456789');

        $this->assertTrue($result);
    }

    public function testProcessDomainWithSuccessfulDeletion(): void
    {
        // Mock finding hosted zone
        $mockListResult = Mockery::mock(Result::class);
        $mockListResult->shouldReceive('get')
                       ->with('HostedZones')
                       ->andReturn([
                           [
                               'Id' => '/hostedzone/Z123456789',
                               'Name' => 'example.com.'
                           ]
                       ]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andReturn($mockListResult);

        // Mock listing records
        $mockRecordsResult = Mockery::mock(Result::class);
        $mockRecordsResult->shouldReceive('get')
                          ->with('ResourceRecordSets')
                          ->andReturn([
                              [
                                  'Name' => 'example.com.',
                                  'Type' => 'NS'
                              ],
                              [
                                  'Name' => 'example.com.',
                                  'Type' => 'SOA'
                              ]
                          ]);

        $this->mockClient->shouldReceive('listResourceRecordSets')
                         ->andReturn($mockRecordsResult);

        // Mock deleting hosted zone
        $this->mockClient->shouldReceive('deleteHostedZone')
                         ->once();

        $this->expectOutputRegex('/Processing domain: \*\*example\.com\*\*/');
        $this->expectOutputRegex('/Successfully deleted Hosted Zone/');

        $result = $this->service->processDomain('example.com');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['skipped']);
        $this->assertEquals('example.com', $result['domain']);
        $this->assertEquals('Z123456789', $result['hosted_zone_id']);
    }

    public function testProcessDomainWithNonExistentDomain(): void
    {
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('HostedZones')
                   ->andReturn([]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andReturn($mockResult);

        $this->expectOutputRegex('/Hosted Zone for \'example\.com\' not found/');

        $result = $this->service->processDomain('example.com');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertEquals('example.com', $result['domain']);
    }

    public function testProcessDomainWithAwsException(): void
    {
        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getMessage')
                     ->andReturn('AWS Error occurred');

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andThrow($awsException);

        $this->expectOutputRegex('/Error deleting \'example\.com\'/');

        $result = $this->service->processDomain('example.com');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['skipped']);
        $this->assertEquals('example.com', $result['domain']);
        $this->assertTrue($result['error']);
    }

    public function testServiceWithDryRunMode(): void
    {
        $dryRunService = new Route53Service($this->mockClient, true);

        // Mock finding hosted zone
        $mockResult = Mockery::mock(Result::class);
        $mockResult->shouldReceive('get')
                   ->with('HostedZones')
                   ->andReturn([
                       [
                           'Id' => '/hostedzone/Z123456789',
                           'Name' => 'example.com.'
                       ]
                   ]);

        $this->mockClient->shouldReceive('listHostedZonesByName')
                         ->andReturn($mockResult);

        // In dry-run mode, no actual AWS calls should be made for deletion
        $this->mockClient->shouldNotReceive('listResourceRecordSets');
        $this->mockClient->shouldNotReceive('deleteHostedZone');

        $this->expectOutputRegex('/\[DRY RUN\] Would delete hosted zone/');

        $result = $dryRunService->processDomain('example.com');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['skipped']);
    }
}
