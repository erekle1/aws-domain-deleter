<?php

use Dotenv\Dotenv;

// This file contains configuration settings for your AWS domain deletion script.
// Environment variables take precedence over these values.
// DO NOT commit this file to a public repository with real credentials.

// Load .env file if it exists
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
   
}

return [
    // AWS Region - checks environment variable first, then fallback
    'aws_region' => getenv('AWS_DEFAULT_REGION') ?: getenv('AWS_REGION') ?: 'eu-central-1',
    
    // AWS Credentials - Environment variables take precedence
    // Priority: Environment Variables > Config File > AWS Profiles > Instance Profile
    'aws_access_key_id' => getenv('AWS_ACCESS_KEY_ID') ?: '',
    'aws_secret_access_key' => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
    'aws_session_token' => getenv('AWS_SESSION_TOKEN') ?: '', // Optional: for temporary credentials
    
    // Path to the CSV file containing domains to delete
    'csv_file_path' => __DIR__ . '/../../domains.csv',
    
    // Advanced AWS settings
    'use_instance_profile' => filter_var(getenv('AWS_USE_INSTANCE_PROFILE') ?: false, FILTER_VALIDATE_BOOLEAN),
    'credential_provider_timeout' => (int)(getenv('AWS_CREDENTIAL_TIMEOUT') ?: 1),
    
    // Domain deletion settings
    'delete_hosted_zones' => filter_var(getenv('DELETE_HOSTED_ZONES') ?: true, FILTER_VALIDATE_BOOLEAN),
    'delete_domain_registrations' => filter_var(getenv('DELETE_DOMAIN_REGISTRATIONS') ?: true, FILTER_VALIDATE_BOOLEAN),
    'permanently_delete_domains' => filter_var(getenv('PERMANENTLY_DELETE_DOMAINS') ?: true, FILTER_VALIDATE_BOOLEAN), // DANGEROUS!
];