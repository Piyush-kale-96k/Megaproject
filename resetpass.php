<?php
// reset-password.php
session_start();
include 'db_connect.php';

$message = '';
$messageType = '';
$token = $_GET['token'] ?? '';
$showForm = false;

if ($token) {
    // Check if the token is valid and not expired
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $showForm = true;
    } else {
        $message = "This password reset link is invalid or has expired.";
        $messageType = 'error';
    }
    $stmt->close();
} else {
    $message = "No reset token provided.";
    $messageType = 'error';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token_from_form = $_POST['token'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = 'error';
        $showForm = true; // Keep form visible
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update the user's password and clear the token
        $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE reset_token = ?");
        $stmt_update->bind_param("ss", $hashed_password, $token_from_form);
        
        if ($stmt_update->execute()) {
            $message = "Your password has been reset successfully!";
            $messageType = 'success';
            $showForm = false; // Hide form on success
        } else {
            $message = "An error occurred. Please try again.";
            $messageType = 'error';
            $showForm = true;
        }
        $stmt_update->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password</title>
    <link rel="stylesheet" href="style2.css" />
    <style>
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; width: 100%; font-size: 14px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .container.single-form { width: 420px; min-height: 400px; }
        .form-container.single { position: static; width: 100%; height: 100%; }
    </style>
</head>

<body>
    <div class="container single-form">
        <div class="form-container single">
            <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <h1>Reset Password</h1>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($showForm): ?>
                    <span>Enter your new password below.</span>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="password" name="password" placeholder="New Password" required />
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required />
                    <button type="submit">Reset Password</button>
                <?php endif; ?>

                <a href="index.php">Back to Login</a>
            </form>
        </div>
    </div>
</body>
</html>

