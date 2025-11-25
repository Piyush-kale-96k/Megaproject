<?php
// FileName: index.php
// This is the main entry point for your entire project.

// Initialize the session
session_start();

// Check if the user is logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // If logged in, show the main homepage content
    include 'homepage.php';
} else {
    // If not logged in, redirect to the login page
    header("location: index.php");
    exit; // Stop further script execution
}
?>

