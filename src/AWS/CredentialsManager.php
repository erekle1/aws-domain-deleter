<?php

namespace App\AWS;

use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;

class CredentialsManager
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get AWS credentials with proper fallback and session token support
     * 
     * @return array|null
     * @throws CredentialsException
     */
    public function getCredentialsConfig(): ?array
    {
        // Config file now handles environment variables directly
        // Priority: Environment Variables > Config File > AWS Profiles > Instance Profile
        
        $accessKey = $this->config['aws_access_key_id'] ?? null;
        $secretKey = $this->config['aws_secret_access_key'] ?? null;
        $sessionToken = $this->config['aws_session_token'] ?? null;

        // If we have explicit credentials, use them
        if ($accessKey && $secretKey && $accessKey !== 'YOUR_AWS_ACCESS_KEY_ID') {
            $credentials = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];

            // Add session token if available (for temporary credentials)
            if ($sessionToken && $sessionToken !== 'YOUR_AWS_SESSION_TOKEN') {
                $credentials['token'] = $sessionToken;
            }

            return $credentials;
        }

        // Check if instance profile is explicitly enabled
        if ($this->config['use_instance_profile'] ?? false) {
            return null; // Let AWS SDK use default provider chain
        }

        throw new CredentialsException(
            "No valid AWS credentials found. Please set environment variables or configure aws_config.php"
        );
    }

    /**
     * Validate that credentials are properly configured
     * 
     * @return bool
     */
    public function validateCredentials(): bool
    {
        try {
            $creds = $this->getCredentialsConfig();
            return $creds !== null || ($this->config['use_instance_profile'] ?? false);
        } catch (CredentialsException $e) {
            return false;
        }
    }

    /**
     * Get credential source description for logging
     * 
     * @return string
     */
    public function getCredentialSource(): string
    {
        // Check environment variables directly
        if (getenv('AWS_ACCESS_KEY_ID')) {
            return 'Environment Variables';
        }

        // Check if config file has non-placeholder values
        $accessKey = $this->config['aws_access_key_id'] ?? null;
        if ($accessKey && $accessKey !== 'YOUR_AWS_ACCESS_KEY_ID') {
            return 'Configuration File';
        }

        // Check instance profile setting
        if ($this->config['use_instance_profile'] ?? false) {
            return 'Instance Profile';
        }

        return 'AWS Default Provider Chain';
    }
}
