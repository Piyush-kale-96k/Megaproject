<?php
// reset_password.php - Handles the final password change using the token.
session_start();
include 'db_connect.php';

// *** CRITICAL FIX: Ensure consistent time zone for validation ***
date_default_timezone_set('UTC'); 

$message = '';
$messageType = '';
$token = $_GET['token'] ?? '';
$showForm = false;
$userId = null; 

// --- 1. Validate Token (Checking if the token is in the database and not expired) ---
if ($token) {
    // CRITICAL: We check for 'reset_token' and 'reset_token_expires_at > NOW()'
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        $showForm = true;
    } else {
        // This is the error message you are seeing. 
        $message = "This password reset link is invalid or has expired. Please try requesting a new link.";
        $messageType = 'error';
    }
    $stmt->close();
} else {
    $message = "No reset token provided. Please use the link sent to your email.";
    $messageType = 'error';
}

// --- 2. Handle Password Update (POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token_from_form = $_POST['token'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = 'error';
        $showForm = true; 
    } else {
        // Re-validate token before processing sensitive update
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
        $stmt_check->bind_param("s", $token_from_form);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
            $message = "Security check failed. The link has expired or is invalid.";
            $messageType = 'error';
            $showForm = false;
            $stmt_check->close();
        } else {
            $stmt_check->close();
            
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear token fields
            $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE reset_token = ?");
            $stmt_update->bind_param("ss", $hashed_password, $token_from_form);
            
            if ($stmt_update->execute()) {
                $message = "Password reset successfully! <a href='index.php' class='underline'>Login here</a>";
                $messageType = 'success';
                $showForm = false; 
            } else {
                $message = "An internal error occurred while updating the password.";
                $messageType = 'error';
                $showForm = true;
            }
            $stmt_update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Set New Password</h2>
        
        <?php if ($message): ?>
            <div class="p-3 mb-4 rounded <?php echo $messageType == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($showForm): ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="space-y-4">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">New Password</label>
                <input type="password" name="password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-slate-500">
            </div>
            <button type="submit" class="w-full bg-slate-700 text-white py-2 rounded-lg hover:bg-slate-800 transition">Update Password</button>
        </form>
        <?php endif; ?>
        
        <?php if (!$showForm && $messageType != 'success'): // Show back to login if error or no token ?>
            <div class="mt-4 text-center">
                <a href="index.php" class="text-sm text-gray-500 hover:text-gray-800">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>