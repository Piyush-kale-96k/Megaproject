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
        $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $name, $email, $hashed_password, $userType);

        if ($stmt_insert->execute()) {
            // Registration successful
            header("location: index.php?signup_success=Registration successful! Please sign in.");
        } else {
            // Registration failed
            header("location: index.php?signup_error=Something went wrong. Please try again.");
        }
        $stmt_insert->close();
    }
    $stmt->close();
}
$conn->close();
?>

