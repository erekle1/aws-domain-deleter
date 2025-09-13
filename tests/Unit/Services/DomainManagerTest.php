<?php

namespace Tests\Unit\Services;

use App\Services\DomainManager;
use Tests\Unit\BaseTestCase;

class DomainManagerTest extends BaseTestCase
{
    private DomainManager $domainManager;
    private string $testCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testCsvPath = __DIR__ . '/../../Fixtures/test-domains.csv';
        $this->domainManager = new DomainManager($this->testCsvPath);
    }

    public function testLoadDomainsReturnsArrayOfDomains(): void
    {
        $domains = $this->domainManager->loadDomains();

        $this->assertIsArray($domains);
        $this->assertCount(6, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('test.com', $domains);
        $this->assertContains('sample.org', $domains);
    }

    public function testLoadDomainsThrowsExceptionForNonExistentFile(): void
    {
        $domainManager = new DomainManager('/non/existent/file.csv');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV file \'/non/existent/file.csv\' not found');

        $domainManager->loadDomains();
    }

    public function testLoadDomainsThrowsExceptionForEmptyFile(): void
    {
        $emptyFile = tempnam(sys_get_temp_dir(), 'empty_domains');
        file_put_contents($emptyFile, '');
        
        $domainManager = new DomainManager($emptyFile);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV file is empty or contains no valid domains');

        try {
            $domainManager->loadDomains();
        } finally {
            unlink($emptyFile);
        }
    }

    public function testLoadDomainsRemovesDuplicates(): void
    {
        $duplicateFile = tempnam(sys_get_temp_dir(), 'duplicate_domains');
        file_put_contents($duplicateFile, "example.com\ntest.com\nexample.com\n");
        
        $domainManager = new DomainManager($duplicateFile);
        $domains = $domainManager->loadDomains();

        $this->assertCount(2, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('test.com', $domains);

        unlink($duplicateFile);
    }

    public function testLoadDomainsRemovesEmptyLines(): void
    {
        $fileWithEmptyLines = tempnam(sys_get_temp_dir(), 'domains_with_empty_lines');
        file_put_contents($fileWithEmptyLines, "example.com\n\ntest.com\n   \n");
        
        $domainManager = new DomainManager($fileWithEmptyLines);
        $domains = $domainManager->loadDomains();

        $this->assertCount(2, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('test.com', $domains);

        unlink($fileWithEmptyLines);
    }

    public function testValidateDomainReturnsTrueForValidDomain(): void
    {
        $this->assertTrue($this->domainManager->validateDomain('example.com'));
        $this->assertTrue($this->domainManager->validateDomain('test.org'));
        $this->assertTrue($this->domainManager->validateDomain('sub.domain.net'));
    }

    public function testValidateDomainReturnsFalseForInvalidDomain(): void
    {
        $this->assertFalse($this->domainManager->validateDomain('invalid-domain'));
        $this->assertFalse($this->domainManager->validateDomain(''));
        $this->assertFalse($this->domainManager->validateDomain('not a domain'));
        $this->assertFalse($this->domainManager->validateDomain('space domain.com'));
    }

    public function testValidateDomainsFiltersInvalidDomains(): void
    {
        $domains = [
            'valid.com',
            'invalid-domain',
            'another.valid.org',
            '',
            'space domain.com'
        ];

        $validDomains = $this->domainManager->validateDomains($domains);

        $this->assertCount(2, $validDomains);
        $this->assertContains('valid.com', $validDomains);
        $this->assertContains('another.valid.org', $validDomains);
    }

    public function testDisplayDomainsOutputsCorrectFormat(): void
    {
        $domains = ['example.com', 'test.org'];

        $this->expectOutputRegex('/Found 2 valid domains to process:/');
        $this->expectOutputRegex('/- example\.com/');
        $this->expectOutputRegex('/- test\.org/');

        $this->domainManager->displayDomains($domains);
    }

    public function testLoadDomainsTrimsWhitespace(): void
    {
        $fileWithWhitespace = tempnam(sys_get_temp_dir(), 'domains_with_whitespace');
        file_put_contents($fileWithWhitespace, "  example.com  \n\t test.com \t\n");
        
        $domainManager = new DomainManager($fileWithWhitespace);
        $domains = $domainManager->loadDomains();

        $this->assertCount(2, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('test.com', $domains);

        unlink($fileWithWhitespace);
    }
}
