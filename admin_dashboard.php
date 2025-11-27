<?php
// admin_dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in and is a teacher OR technician.
$user_id = $_SESSION["user_id"] ?? 0;
$user_type = $_SESSION["user_type"] ?? '';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($user_type !== 'teacher' && $user_type !== 'technician')) {
    header("location: homepage.php?page=home");
    exit;
}

include 'db_connect.php'; 

$message = "";
$success_message = '';

// --- Handle Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = $_POST['report_id'];
    $computer_id = $_POST['computer_id'];
    $new_status = $_POST['new_status'];
    
    // Authorization Check: Ensure the user manages this lab before updating
    // This query verifies that the computer belongs to a lab managed by the current user.
    $auth_stmt = $conn->prepare("SELECT l.id FROM labs l JOIN computers c ON l.id = c.lab_id WHERE c.id = ? AND l.manager_id = ?");
    $auth_stmt->bind_param("ii", $computer_id, $user_id);
    $auth_stmt->execute();
    if ($auth_stmt->get_result()->num_rows == 0) {
        $_SESSION['success_message'] = "Error: You are not authorized to update reports for this lab.";
        $auth_stmt->close();
        header("Location: homepage.php?page=admin_dashboard");
        exit;
    }
    $auth_stmt->close();


    // Update the report status
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $report_id);
    $stmt->execute();
    $stmt->close();

    // Determine the new status for the computer
    $computer_status = 'Reported'; 
    if ($new_status === 'Resolved') {
        $computer_status = 'OK';
    } elseif ($new_status === 'Reworking') {
        $computer_status = 'Reworking';
    }

    // Update the computer's status
    $stmt_computer = $conn->prepare("UPDATE computers SET status = ? WHERE id = ?");
    $stmt_computer->bind_param("si", $computer_status, $computer_id);
    $stmt_computer->execute();
    $stmt_computer->close();

    $_SESSION['success_message'] = "Report status has been updated successfully!";
    header("Location: homepage.php?page=admin_dashboard");
    exit;
}

// Check for a success message from the session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); 
}

$reports = [];
// --- CRITICAL QUERY FIX: Filter by manager_id ---
$sql = "SELECT 
            r.id AS report_id,
            r.status AS report_status,
            r.description,
            r.category,
            r.created_at,
            c.id AS computer_id,
            c.pc_number,
            l.name AS lab_name,
            u.name AS user_name
        FROM reports r
        JOIN users u ON r.user_id = u.id
        JOIN computers c ON r.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        WHERE l.manager_id = ? 
        ORDER BY FIELD(r.status, 'Reported', 'Reworking', 'Resolved'), r.created_at DESC";

$stmt_reports = $conn->prepare($sql);
$stmt_reports->bind_param("i", $user_id);
$stmt_reports->execute();
$result = $stmt_reports->get_result();
if ($result) {
    $reports = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
$stmt_reports->close();

?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600 mt-1">Manage all submitted fault reports for your assigned labs.</p>
    </header>

    <!-- Success Message Display -->
    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6 shadow-sm" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6">
        <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PC Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporter</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-500">No reports found for your assigned labs.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($report['lab_name']) . ' - PC ' . htmlspecialchars($report['pc_number']); ?></div>
                                    <div class="text-xs text-gray-500">Report ID: <?php echo $report['report_id']; ?></div>
                                    <div class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($report['user_name']); ?></td>
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-xs font-medium text-gray-700"><?php echo htmlspecialchars($report['category']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($report['description']); ?>"><?php echo htmlspecialchars($report['description']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full 
                                        <?php 
                                            if ($report['report_status'] == 'Reported') echo 'bg-red-200 text-red-900';
                                            elseif ($report['report_status'] == 'Reworking') echo 'bg-yellow-200 text-yellow-900';
                                            else echo 'bg-green-200 text-green-900';
                                        ?>">
                                        <?php echo htmlspecialchars($report['report_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" action="?page=admin_dashboard" class="flex flex-col space-y-2">
                                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                        <input type="hidden" name="computer_id" value="<?php echo $report['computer_id']; ?>">
                                        <select name="new_status" class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 p-1.5">
                                            <option value="Reported" <?php if ($report['report_status'] == 'Reported') echo 'selected'; ?>>Reported</option>
                                            <option value="Reworking" <?php if ($report['report_status'] == 'Reworking') echo 'selected'; ?>>Reworking</option>
                                            <option value="Resolved" <?php if ($report['report_status'] == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" name="update_status" class="w-full justify-center py-1.5 px-2 border border-transparent text-xs font-medium rounded text-white bg-slate-700 hover:bg-slate-800 transition">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>