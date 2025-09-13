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
$config = require __DIR__ . '/config/aws_config.php';

// Parse command line arguments
$options = UserInterface::parseArguments($argv);

// Create and run application
$app = new Application($config, $options);
exit($app->run());
