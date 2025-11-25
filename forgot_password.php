<?php
// forgot_password.php - User enters email to request a reset link.
session_start();
include 'db_connect.php';

$message = '';
$messageType = '';

// *** REMOVED: The complex getBaseUrl function is removed. ***
// *** ADDED: We define the base path explicitly for XAMPP/Magaproject. ***
$PROJECT_BASE_PATH = "http://localhost/Magaproject/";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate a unique token (secure random string)
        $token = bin2hex(random_bytes(50));
        
        // Expiration time set to 1 day for reliability
        $expires_at = date("Y-m-d H:i:s", strtotime('+1 day'));
        
        // *** CRITICAL FIX: Use the confirmed project path ***
        $resetLink = $PROJECT_BASE_PATH . "reset_password.php?token=" . $token;

        // Save token to database
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
        $update->bind_param("sss", $token, $expires_at, $email);
        
        if ($update->execute()) {
            // Success response (SIMULATED EMAIL)
            $message = "A password reset link has been sent to your email address (Simulated link below).";
            $message .= "<br>Generated Link: <a href='$resetLink' class='text-blue-600 underline font-medium'>Click here to reset your password</a>";
            $messageType = 'success';
        } else {
            // DB Error logging
            $message = "Database Error: Failed to save the token. MySQL Error: " . $conn->error;
            $messageType = 'error';
        }
    } else {
        // Always provide a non-specific success message for security
        $message = "If an account with that email exists, a password reset link has been sent.";
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Forgot Password</h2>
        
        <?php if ($message): ?>
            <div class="p-3 mb-4 rounded <?php echo $messageType == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST" class="space-y-4">
            <p class="text-gray-600 text-sm">Enter your account's email address and we will send you a link to reset your password.</p>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-slate-500">
            </div>
            <button type="submit" class="w-full bg-slate-700 text-white py-2 rounded-lg hover:bg-slate-800 transition">Request Reset Link</button>
        </form>
        <div class="mt-4 text-center">
            <a href="index.php" class="text-sm text-gray-500 hover:text-gray-800">Back to Login</a>
        </div>
    </div>
</body>
</html>