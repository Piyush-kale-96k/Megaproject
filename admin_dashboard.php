<?php
// admin_dashboard.php (Content Fragment)

// Session is already started by homepage.php, but we check just in case
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure the user is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== 'teacher') {
    echo "<script>window.location.href='homepage.php?page=home';</script>";
    exit;
}

include_once 'db_connect.php';

// --- Handle Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = $_POST['report_id'];
    $computer_id = $_POST['computer_id'];
    $new_status = $_POST['new_status'];

    // Update the report status
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $report_id);
    $stmt->execute();
    $stmt->close();

    // Determine the new status for the computer
    $computer_status = 'Reported'; // Default
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

    $success_message = "Report updated successfully!";
}

// Fetch Reports
$reports = [];
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
        ORDER BY FIELD(r.status, 'Reported', 'Reworking', 'Resolved'), r.created_at DESC";

$result = mysqli_query($conn, $sql);
if ($result) {
    $reports = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600 mt-1">Manage all submitted fault reports.</p>
    </header>

    <!-- Success Message -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm p-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PC Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporter</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-500">No reports found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($report['lab_name']); ?></div>
                                    <div class="text-sm text-gray-500">PC <?php echo htmlspecialchars($report['pc_number']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($report['user_name']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium bg-gray-100 rounded-full mb-1">
                                        <?php echo htmlspecialchars($report['category']); ?>
                                    </span>
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($report['description']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            if ($report['report_status'] == 'Reported') echo 'bg-red-100 text-red-800';
                                            elseif ($report['report_status'] == 'Reworking') echo 'bg-yellow-100 text-yellow-800';
                                            else echo 'bg-green-100 text-green-800';
                                        ?>">
                                        <?php echo htmlspecialchars($report['report_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <form method="POST" action="?page=admin_dashboard" class="flex flex-col space-y-2">
                                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                        <input type="hidden" name="computer_id" value="<?php echo $report['computer_id']; ?>">
                                        <select name="new_status" class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="Reported" <?php if ($report['report_status'] == 'Reported') echo 'selected'; ?>>Reported</option>
                                            <option value="Reworking" <?php if ($report['report_status'] == 'Reworking') echo 'selected'; ?>>Reworking</option>
                                            <option value="Resolved" <?php if ($report['report_status'] == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" name="update_status" class="w-full justify-center py-1 px-2 border border-transparent text-xs font-medium rounded text-white bg-slate-700 hover:bg-slate-800">
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