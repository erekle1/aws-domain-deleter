<?php
require 'vendor/autoload.php';
$configPath = __DIR__ . '/../src/config/aws_config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
} else {
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
echo 'AWS_KEY: ' . $config['aws_access_key_id'] . PHP_EOL;
echo 'DELETE_HOSTED_ZONES: ' . ($config['delete_hosted_zones'] ? 'true' : 'false') . PHP_EOL;
echo 'DELETE_DOMAIN_REGISTRATIONS: ' . ($config['delete_domain_registrations'] ? 'true' : 'false') . PHP_EOL;
