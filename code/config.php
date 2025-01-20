<?php
// Load the JSON config file
$configPath = __DIR__ . '/config.json';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo "<h2>Error: Configuration File Not Found</h2>";
    echo "<p>It looks like <code>config.json</code> is missing or misplaced. Please make sure it is located in the following path:</p>";
    echo "<p><strong>" . htmlspecialchars($configPath) . "</strong></p>";
    exit;
}

$configContents = file_get_contents($configPath);
$config = json_decode($configContents, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo "<h2>Error: Invalid Configuration Format</h2>";
    echo "<p>Your <code>config.json</code> file contains invalid JSON.</p>";
    echo "<p><strong>Hint:</strong> Check for missing commas, mismatched quotes, or extra braces.</p>";
    echo "<p>Error details: <code>" . htmlspecialchars(json_last_error_msg()) . "</code></p>";
    echo "<p>Tip: Use a JSON validator tool like <a href='https://jsonlint.com/' target='_blank'>jsonlint.com</a> to check for syntax errors.</p>";
    exit;
}

// Function to get environment variable or fallback to JSON config value
function getEnvOrConfig($envKey, $configKey, $default = null) {
    $envValue = getenv($envKey); 
    return $envValue !== false ? $envValue : ($default ?? ($configKey ?? null));
}

// Configuration variables
$use_local_images = getEnvOrConfig('BF_USE_LOCAL_IMAGES', $config['use_local_images'] ?? 0);
$dbhost = getEnvOrConfig('BF_DB_HOST', $config['dbhost'] ?? 'localhost');
$dbport = getEnvOrConfig('BF_DB_PORT', $config['dbport'] ?? '26257');
$db = getEnvOrConfig('BF_DB_NAME', $config['db'] ?? 'bf');
$dbuser = getEnvOrConfig('BF_DB_USER', $config['dbuser'] ?? 'bfuser');
$dbpassw = getEnvOrConfig('BF_DB_PASS', $config['dbpassw'] ?? '');
$webhost = getEnvOrConfig('BF_WEBHOST', $config['webhost'] ?? 'localhost');
$imagepath = getEnvOrConfig('BF_IMAGE_PATH', $config['imagepath'] ?? '/var/www/images');
$frontpage_limit = getEnvOrConfig('BF_FRONTPAGE_LIMIT', $config['frontpage_limit'] ?? '1000');
$weburl = 'http://' . $webhost;

// Memcache configuration
$memcache_enabled_pictures = 0;
$memcache_enabled = 0;
$memcache_server = null;

if (getenv('BF_MEMCACHE_SERVER')) {
    $memcache_enabled_pictures = 1;
    $memcache_enabled = 1;
    $memcache_server = getenv('BF_MEMCACHE_SERVER');
}

// Configuration success message (can be enabled for debugging)
// echo "<p>Configuration loaded successfully!</p>";

?>
