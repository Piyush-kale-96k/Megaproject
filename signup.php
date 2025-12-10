<?php
// FileName: signup.php
include 'db_connect.php';

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $userType = $_POST['userType'];
    // NEW: Collect Branch data from the form
    $branch = $_POST['branch']; 

    // --- NEW VALIDATION: Check if userType is allowed (for stability) ---
    $allowedUserTypes = ['student', 'teacher', 'technician'];
    if (!in_array($userType, $allowedUserTypes)) {
        header("location: index.php?signup_error=Invalid user type selected.");
        exit;
    }
    // --- END NEW VALIDATION ---
    
    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Email already exists
        header("location: index.php?signup_error=An account with this email already exists.");
    } else {
        // Email doesn't exist, insert new user
        // CRITICAL FIX: Insert into the new `branch` column
        $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, user_type, branch) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sssss", $name, $email, $hashed_password, $userType, $branch);

        if ($stmt_insert->execute()) {
            // Registration successful
            header("location: index.php?signup_success=Registration successful! Please sign in.");
        } else {
            // Registration failed
            header("location: index.php?signup_error=Something went wrong. Please try again. (DB Error)");
        }
        $stmt_insert->close();
    }
    $stmt->close();
}
$conn->close();
?>