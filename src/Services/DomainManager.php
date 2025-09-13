<?php

namespace App\Services;

class DomainManager
{
    private string $csvFilePath;

    public function __construct(string $csvFilePath)
    {
        $this->csvFilePath = $csvFilePath;
    }

    /**
     * Load domains from CSV file
     * 
     * @return array
     * @throws \Exception
     */
    public function loadDomains(): array
    {
        if (!file_exists($this->csvFilePath)) {
            throw new \Exception("CSV file '{$this->csvFilePath}' not found");
        }

        $domains = array_map('trim', file($this->csvFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $domains = array_filter($domains); // Remove empty lines
        $domains = array_unique($domains); // Remove duplicates

        if (empty($domains)) {
            throw new \Exception("CSV file is empty or contains no valid domains");
        }

        return $domains;
    }

    /**
     * Validate domain format
     * 
     * @param string $domain
     * @return bool
     */
    public function validateDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * Filter and validate domains
     * 
     * @param array $domains
     * @return array
     */
    public function validateDomains(array $domains): array
    {
        $validDomains = [];
        $invalidDomains = [];

        foreach ($domains as $domain) {
            if ($this->validateDomain($domain)) {
                $validDomains[] = $domain;
            } else {
                $invalidDomains[] = $domain;
            }
        }

        if (!empty($invalidDomains)) {
            echo "⚠️ Found invalid domains (will be skipped):\n";
            foreach ($invalidDomains as $invalid) {
                echo "  - {$invalid}\n";
            }
            echo "\n";
        }

        return $validDomains;
    }

    /**
     * Display domains list
     * 
     * @param array $domains
     * @return void
     */
    public function displayDomains(array $domains): void
    {
        echo "Found " . count($domains) . " valid domains to process:\n";
        foreach ($domains as $domain) {
            echo "- {$domain}\n";
        }
        echo "\n";
    }
}
