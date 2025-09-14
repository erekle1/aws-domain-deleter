<?php

/**
 * AWS Domain Manager
 * 
 * A comprehensive PHP toolkit for managing AWS Route 53 domains.
 * Supports both domain deletion and contact information updates.
 * 
 * Usage:
 *   # Domain Deletion
 *   php aws-domain-manager.php --delete-domains --dry-run    # Preview mode
 *   php aws-domain-manager.php --delete-domains              # Interactive deletion
 *   php aws-domain-manager.php --delete-domains --force      # Force deletion
 * 
 *   # Contact Updates
 *   php aws-domain-manager.php --update-contacts --admin-contact --dry-run
 *   php aws-domain-manager.php --update-contacts --admin-contact --tech-contact
 * 
 * @author LeadHub Team
 * @version 3.0
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
