<?php

/**
 * AWS Route 53 Domain Deleter
 * 
 * A structured PHP script for safely deleting multiple AWS Route 53 hosted zones
 * for domains listed in a CSV file.
 * 
 * Usage:
 *   php delete.php --dry-run    # Preview mode (recommended first)
 *   php delete.php              # Interactive deletion
 *   php delete.php --force      # Force deletion without confirmation
 * 
 * @author LeadHub Team
 * @version 2.0
 */

require 'vendor/autoload.php';

use App\Application;
use App\Services\UserInterface;

// Load configuration
$configPath = __DIR__ . '/src/config/aws_config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
    // Fallback configuration for testing/CI environments
    $config = [
        'aws_region' => 'eu-central-1',
        'aws_access_key_id' => '',
        'aws_secret_access_key' => '',
        'aws_session_token' => '',
        'csv_file_path' => __DIR__ . '/domains.csv',
        'use_instance_profile' => false,
        'credential_provider_timeout' => 1,
        'delete_hosted_zones' => true,
        'delete_domain_registrations' => false,
        'permanently_delete_domains' => false,
    ];
}

// Parse command line arguments
$options = UserInterface::parseArguments($argv);

// Create and run application
$app = new Application($config, $options);
exit($app->run());
