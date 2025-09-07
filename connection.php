<?php
$host = "capstoneproject.mysql.database.azure.com";
$dbname = "optimabank";
$username = "capstone";  // ✅ just username, no @server
$password = "Project123";
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
