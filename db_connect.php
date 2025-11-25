<?php
// FileName: db_connect.php

// --- Production-Ready Database Connection ---

$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password
$dbname = "login_db"; // Your database name

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    // In a real application, you would log this error instead of showing it to the user.
    die("Connection failed: " . mysqli_connect_error());
}
?>

