<?php
// login.php

// Start the session
session_start();

// Include your database connection file
include 'db_connect.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $userType = $_POST['userType'];

    // Prepare a statement to prevent SQL injection
    // NEW: Selecting the 'branch' column
    $stmt = $conn->prepare("SELECT id, name, password, user_type, branch FROM users WHERE email = ? AND user_type = ?");
    $stmt->bind_param("ss", $email, $userType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Password is correct, so start a new session and save user data
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Store data in session variables
            $_SESSION["loggedin"] = true;
            $_SESSION["user_id"] = $user['id']; 
            $_SESSION["name"] = $user['name'];
            $_SESSION["user_type"] = $user['user_type'];
            // NEW: Store Branch
            $_SESSION["branch"] = $user['branch']; 

            // Redirect user to the main homepage
            header("location: homepage.php");
            exit;
        } else {
            // Incorrect password
            header("location: index.php?login_error=Invalid email or password.");
            exit;
        }
    } else {
        // No user found with that email and user type
        header("location: index.php?login_error=Invalid email or password.");
        exit;
    }

    $stmt->close();
}

$conn->close();