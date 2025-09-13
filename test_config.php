<?php
require 'vendor/autoload.php';
$config = require 'config/aws_config.php';
echo 'AWS_KEY: ' . $config['aws_access_key_id'] . PHP_EOL;
echo 'DELETE_HOSTED_ZONES: ' . ($config['delete_hosted_zones'] ? 'true' : 'false') . PHP_EOL;
echo 'DELETE_DOMAIN_REGISTRATIONS: ' . ($config['delete_domain_registrations'] ? 'true' : 'false') . PHP_EOL;
