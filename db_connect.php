<?php
// FileName: db_connect.php

// CRITICAL FIX: Ensure no PHP warnings/errors leak out and corrupt the JSON response.
// This is the safest way to prevent the "Invalid response format" error
// without breaking the main application's output (CSS/HTML).
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password
$dbname = "login_db"; // Your database name

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    // If connection fails, output a fatal error
    // IMPORTANT: Even here, the die() call must NOT output anything that breaks the JSON,
    // but since this file is included everywhere, we keep it simple for general use.
    // However, on an AJAX endpoint failure, we must stop immediately.
    die("Connection failed: " . mysqli_connect_error());
}

// IMPORTANT: Do NOT add a closing ?> tag or any whitespace after this line.