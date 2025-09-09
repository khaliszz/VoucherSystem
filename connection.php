<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // or use project root if .env is there
$dotenv->load();

$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$sslcert = __DIR__ . "/DigiCertGlobalRootCA.crt.pem"; // use relative path

$dsn = "mysql:host=$host;dbname=$dbname;port=3306;charset=utf8mb4";

$options = [
    PDO::MYSQL_ATTR_SSL_CA => $sslcert,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // important for Windows
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
];

try {
    $conn = new PDO($dsn, $username, $password, $options);
    // echo "✅ Connected successfully with PDO + SSL!";
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>
