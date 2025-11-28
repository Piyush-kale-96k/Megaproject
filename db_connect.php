<?php
// FileName: db_connect.php
// This file establishes the database connection object ($conn).

// CRITICAL FIX: Ensure no PHP warnings/errors leak out.
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password
$dbname = "login_db"; // Your database name

// Use the robust, object-oriented MySQLi connection method (new mysqli)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection and immediately trigger a fatal error if connection fails
if ($conn->connect_error) {
    // IMPORTANT: If you see this error, ensure your MySQL server (in XAMPP/WAMP) is running.
    die("Database Connection failed: " . $conn->connect_error);
}

// Set character set to UTF8 for proper handling of special characters
$conn->set_charset("utf8mb4");
