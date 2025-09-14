<?php

namespace Tests\Unit\Services;

use App\Services\ContactUpdateService;
use Aws\Route53Domains\Route53DomainsClient;
use Aws\Result;
use Aws\Exception\AwsException;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContactUpdateServiceTest extends TestCase
{
    private $mockRoute53DomainsClient;
    private $contactInfo;
    private $csvFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRoute53DomainsClient = Mockery::mock(Route53DomainsClient::class);
        
        $this->contactInfo = [
            'admin_contact' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'contactType' => 'PERSON',
                'organizationName' => 'Test Organization',
                'addressLine1' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'TS',
                'countryCode' => 'US',
                'zipCode' => '12345',
                'phoneNumber' => '+1.5551234567',
                'email' => 'admin@test.com',
                'extraParams' => []
            ],
            'registrant_contact' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'contactType' => 'PERSON',
                'organizationName' => 'Test Organization',
                'addressLine1' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'TS',
                'countryCode' => 'US',
                'zipCode' => '12345',
                'phoneNumber' => '+1.5551234567',
                'email' => 'registrant@test.com',
                'extraParams' => []
            ],
            'tech_contact' => [
                'firstName' => 'Tech',
                'lastName' => 'Support',
                'contactType' => 'PERSON',
                'organizationName' => 'Test Organization',
                'addressLine1' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'TS',
                'countryCode' => 'US',
                'zipCode' => '12345',
                'phoneNumber' => '+1.5551234567',
                'email' => 'tech@test.com',
                'extraParams' => []
            ]
        ];

        $this->csvFilePath = __DIR__ . '/../../Fixtures/test_domains.csv';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoadDomainsFromCsvSuccess()
    {
        // Create test CSV file
        $csvContent = "domain_name,update_admin,update_registrant,update_tech\n";
        $csvContent .= "example.com,true,true,true\n";
        $csvContent .= "test-domain.com,false,true,false\n";
        $csvContent .= "another-domain.org,true,false,true\n";
        
        file_put_contents($this->csvFilePath, $csvContent);

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        $domains = $service->loadDomainsFromCsv();

        $this->assertCount(3, $domains);
        $this->assertEquals('example.com', $domains[0]['domain_name']);
        $this->assertTrue($domains[0]['update_admin']);
        $this->assertTrue($domains[0]['update_registrant']);
        $this->assertTrue($domains[0]['update_tech']);

        $this->assertEquals('test-domain.com', $domains[1]['domain_name']);
        $this->assertFalse($domains[1]['update_admin']);
        $this->assertTrue($domains[1]['update_registrant']);
        $this->assertFalse($domains[1]['update_tech']);

        // Clean up
        unlink($this->csvFilePath);
    }

    public function testLoadDomainsFromCsvFileNotFound()
    {
        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, '/nonexistent/file.csv');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV file not found: /nonexistent/file.csv');
        
        $service->loadDomainsFromCsv();
    }

    public function testUpdateDomainContactsDryRun()
    {
        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $result = $service->updateDomainContacts('example.com', ['admin', 'tech'], true);
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('DRY RUN', $result['message']);
        $this->assertContains('admin', $result['updated_contacts']);
        $this->assertContains('tech', $result['updated_contacts']);
        $this->assertEmpty($result['errors']);
    }

    public function testUpdateDomainContactsSuccess()
    {
        $this->mockRoute53DomainsClient
            ->shouldReceive('getDomainDetail')
            ->with(['DomainName' => 'example.com'])
            ->andReturn(new Result(['DomainName' => 'example.com']));

        $this->mockRoute53DomainsClient
            ->shouldReceive('updateDomainContact')
            ->with([
                'DomainName' => 'example.com',
                'AdminContact' => $this->contactInfo['admin_contact'],
                'TechContact' => $this->contactInfo['tech_contact']
            ])
            ->andReturn(new Result(['OperationId' => 'test-operation-id']));

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $result = $service->updateDomainContacts('example.com', ['admin', 'tech'], false);
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Successfully updated', $result['message']);
        $this->assertContains('admin', $result['updated_contacts']);
        $this->assertContains('tech', $result['updated_contacts']);
        $this->assertEmpty($result['errors']);
    }

    public function testUpdateDomainContactsAwsException()
    {
        $this->mockRoute53DomainsClient
            ->shouldReceive('getDomainDetail')
            ->with(['DomainName' => 'example.com'])
            ->andThrow(new AwsException('Domain not found', Mockery::mock('Aws\CommandInterface')));

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $result = $service->updateDomainContacts('example.com', ['admin'], false);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to update contacts', $result['message']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testProcessDomainsWithAllContactTypes()
    {
        // Create test CSV file
        $csvContent = "domain_name,update_admin,update_registrant,update_tech\n";
        $csvContent .= "example.com,true,true,true\n";
        
        file_put_contents($this->csvFilePath, $csvContent);

        $this->mockRoute53DomainsClient
            ->shouldReceive('getDomainDetail')
            ->with(['DomainName' => 'example.com'])
            ->andReturn(new Result(['DomainName' => 'example.com']));

        $this->mockRoute53DomainsClient
            ->shouldReceive('updateDomainContact')
            ->with([
                'DomainName' => 'example.com',
                'AdminContact' => $this->contactInfo['admin_contact'],
                'RegistrantContact' => $this->contactInfo['registrant_contact'],
                'TechContact' => $this->contactInfo['tech_contact']
            ])
            ->andReturn(new Result(['OperationId' => 'test-operation-id']));

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $options = [
            'dry_run' => false,
            'admin_contact' => true,
            'registrant_contact' => true,
            'tech_contact' => true
        ];

        $results = $service->processDomains($options);
        
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertStringContainsString('Successfully updated', $results[0]['message']);

        // Clean up
        unlink($this->csvFilePath);
    }

    public function testProcessDomainsWithSkippedDomains()
    {
        // Create test CSV file
        $csvContent = "domain_name,update_admin,update_registrant,update_tech\n";
        $csvContent .= "example.com,false,false,false\n";
        
        file_put_contents($this->csvFilePath, $csvContent);

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $options = [
            'dry_run' => false,
            'admin_contact' => true,
            'registrant_contact' => false,
            'tech_contact' => false
        ];

        $results = $service->processDomains($options);
        
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[0]['skipped']);
        $this->assertStringContainsString('No contacts to update', $results[0]['message']);

        // Clean up
        unlink($this->csvFilePath);
    }

    public function testProcessDomainsDryRun()
    {
        // Create test CSV file
        $csvContent = "domain_name,update_admin,update_registrant,update_tech\n";
        $csvContent .= "example.com,true,true,true\n";
        
        file_put_contents($this->csvFilePath, $csvContent);

        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $options = [
            'dry_run' => true,
            'admin_contact' => true,
            'registrant_contact' => true,
            'tech_contact' => true
        ];

        $results = $service->processDomains($options);
        
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertStringContainsString('DRY RUN', $results[0]['message']);

        // Clean up
        unlink($this->csvFilePath);
    }

    public function testDisplaySummary()
    {
        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $results = [
            [
                'domain' => 'example.com',
                'success' => true,
                'skipped' => false,
                'message' => 'Successfully updated admin, tech contact(s) for example.com'
            ],
            [
                'domain' => 'test.com',
                'success' => false,
                'skipped' => false,
                'message' => 'Failed to update contacts for test.com'
            ],
            [
                'domain' => 'skip.com',
                'success' => true,
                'skipped' => true,
                'message' => 'No contacts to update for skip.com'
            ]
        ];

        // Capture output
        ob_start();
        $service->displaySummary($results, false);
        $output = ob_get_clean();

        $this->assertStringContainsString('CONTACT UPDATE SUMMARY', $output);
        $this->assertStringContainsString('Total domains processed: 3', $output);
        $this->assertStringContainsString('Successful updates: 1', $output);
        $this->assertStringContainsString('Failed updates: 1', $output);
        $this->assertStringContainsString('Skipped domains: 1', $output);
        $this->assertStringContainsString('Failed domains:', $output);
        $this->assertStringContainsString('test.com', $output);
    }

    public function testDisplaySummaryDryRun()
    {
        $service = new ContactUpdateService($this->mockRoute53DomainsClient, $this->contactInfo, $this->csvFilePath);
        
        $results = [
            [
                'domain' => 'example.com',
                'success' => true,
                'skipped' => false,
                'message' => 'DRY RUN: Would update contacts for example.com'
            ]
        ];

        // Capture output
        ob_start();
        $service->displaySummary($results, true);
        $output = ob_get_clean();

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('no actual changes were made', $output);
    }
}
