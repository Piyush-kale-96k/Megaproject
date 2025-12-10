<?php
// pc_history.php (Content Fragment)
// Displays a history of all reports for a single computer, showing both the original report 
// and the maintenance notes.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// FIX: Use include_once for stability
include_once 'db_connect.php';

// Security check: only allow staff (Teacher/Technician) to view history
$user_type = $_SESSION['user_type'] ?? 'student';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($user_type === 'student')) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Permission denied. Only staff can view PC history.</div>";
    return;
}

$computer_id = isset($_GET['computer_id']) ? (int)$_GET['computer_id'] : 0;

if ($computer_id === 0) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Invalid Computer ID provided.</div>";
    return;
}

// Fetch PC details
$pc_details = null;
$details_stmt = $conn->prepare("
    SELECT c.pc_number, l.name AS lab_name 
    FROM computers c 
    JOIN labs l ON c.lab_id = l.id 
    WHERE c.id = ?
");
$details_stmt->bind_param("i", $computer_id);
$details_stmt->execute();
$details_result = $details_stmt->get_result();
if ($details_result->num_rows > 0) {
    $pc_details = $details_result->fetch_assoc();
}
$details_stmt->close();

if (!$pc_details) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Computer not found.</div>";
    return;
}

// Fetch all reports for this computer, ordered by newest first
$reports = [];
$reports_query = "
    SELECT r.description, r.resolution_note, r.category, r.status, r.created_at, u.name AS reporter_name
    FROM reports r
    JOIN users u ON r.user_id = u.id
    WHERE r.computer_id = ?
    ORDER BY r.created_at DESC
";
$reports_stmt = $conn->prepare($reports_query);
$reports_stmt->bind_param("i", $computer_id);
$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();
if ($reports_result) {
    $reports = mysqli_fetch_all($reports_result, MYSQLI_ASSOC);
}
$reports_stmt->close();
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto bg-white p-6 md:p-8 rounded-xl shadow-lg border border-gray-200">
        
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <h1 class="text-2xl font-bold text-gray-900">
                Report History: <?php echo htmlspecialchars($pc_details['lab_name']) . ' - PC ' . htmlspecialchars($pc_details['pc_number']); ?>
            </h1>
            <a href="?page=labs_view" class="text-sm text-blue-600 hover:underline inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Lab Overview
            </a>
        </div>
        
        <?php if (empty($reports)): ?>
            <div class="p-4 bg-gray-50 text-gray-600 rounded-lg text-center border-dashed border-2 border-gray-300">
                <p class="text-lg font-medium">No report history found for this computer.</p>
                <p class="text-sm mt-1">This PC has never had a fault reported.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($reports as $report): ?>
                    <?php
                        $status_color = 'bg-gray-100 text-gray-800 border-gray-300';
                        $is_internal = $report['category'] === 'Internal Maintenance Log';
                        
                        if ($report['status'] === 'Resolved') {
                            $status_color = 'bg-green-50 text-green-800 border-green-300';
                        } elseif ($report['status'] === 'Reported') {
                            $status_color = 'bg-red-50 text-red-800 border-red-300';
                        } elseif ($report['status'] === 'Reworking') {
                            $status_color = 'bg-yellow-50 text-yellow-800 border-yellow-300';
                        }
                        
                        if ($is_internal) {
                            $report_title = 'Internal Log: ' . htmlspecialchars($report['category']);
                            $status_color = 'bg-blue-50 text-blue-800 border-blue-300';
                        } else {
                            $report_title = htmlspecialchars($report['category']) . ' Issue';
                        }
                        
                        // Condition to show original report description (must not be empty, and not the internal marker)
                        $show_original_description = !empty(trim($report['description'])) && ($report['description'] !== '[INTERNAL NOTE: Maintenance Logged]');
                        $show_resolution_note = !empty(trim($report['resolution_note']));
                    ?>
                    <div class="p-4 border rounded-lg shadow-sm <?php echo $status_color; ?>">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-semibold text-lg text-gray-900"><?php echo $report_title; ?></span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full 
                                <?php 
                                    if ($report['status'] === 'Resolved') echo 'bg-green-200 text-green-900';
                                    elseif ($report['status'] === 'Reported') echo 'bg-red-200 text-red-900';
                                    else echo 'bg-yellow-200 text-yellow-900';
                                ?>">
                                <?php echo htmlspecialchars($report['status']); ?>
                            </span>
                        </div>

                        <!-- Display Original Fault Description -->
                        <?php if ($show_original_description): ?>
                            <div class="mb-2 p-3 bg-white rounded-md border border-gray-100 shadow-inner">
                                <strong class="text-sm block text-gray-800 mb-1">Fault Reported:</strong>
                                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Technician Resolution Note -->
                        <?php if ($show_resolution_note): ?>
                             <div class="mb-2 p-3 bg-white rounded-md border border-gray-100 shadow-inner">
                                <strong class="text-sm block text-gray-800 mb-1">Technician Note:</strong>
                                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($report['resolution_note'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="text-xs text-gray-500 border-t border-<?php echo $is_internal ? 'blue' : ($report['status'] === 'Resolved' ? 'green' : ($report['status'] === 'Reported' ? 'red' : 'yellow')); ?>-200 pt-2 mt-2 flex justify-between">
                            <span>Logged By: **<?php echo htmlspecialchars($report['reporter_name']); ?>**</span>
                            <span>Date: <?php echo date('M d, Y H:i A', strtotime($report['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>