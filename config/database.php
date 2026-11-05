<?php

date_default_timezone_set('Asia/Kolkata');

// Load local credentials if available (gitignored), otherwise check environment
if (file_exists(__DIR__ . "/aiven_credentials.php")) {
    require_once __DIR__ . "/aiven_credentials.php";
} else {
    $host     = getenv("DB_HOST") ?: "minor-project-naimish-eb49.d.aivencloud.com";
    $port     = (int)(getenv("DB_PORT") ?: 12294);
    $username = getenv("DB_USER") ?: "avnadmin";
    $password = getenv("DB_PASS") ?: "";
    $database = getenv("DB_NAME") ?: "My_project";
}

// 1. Initialize mysqli
$conn = mysqli_init();

// 2. Point to ca.pem (supports both config/ca.pem and project root ca.pem)
$ca_cert = null;
if (file_exists(__DIR__ . "/ca.pem")) {
    $ca_cert = __DIR__ . "/ca.pem";
} elseif (file_exists(dirname(__DIR__) . "/ca.pem")) {
    $ca_cert = dirname(__DIR__) . "/ca.pem";
}

if ($ca_cert) {
    $conn->ssl_set(NULL, NULL, $ca_cert, NULL, NULL);
}

// 3. Connect with SSL and the Port with safe error handling
try {
    $flags = $ca_cert ? MYSQLI_CLIENT_SSL : 0;
    $success = @$conn->real_connect(
        $host,
        $username,
        $password,
        $database,
        $port,
        NULL,
        $flags
    );

    if (!$success || $conn->connect_error) {
        die("Database connection failed: " . ($conn->connect_error ?: "Unknown connection error"));
    }
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+05:30'");

// Note: Do not echo anything here, as output before header() breaks login redirects and session management.

?>