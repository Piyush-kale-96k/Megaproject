<?php
// update_report_status.php
// This endpoint handles the AJAX request from labs_view.php to update report status.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set header for JSON response
header('Content-Type: application/json');

// --- 1. Security Check: Only allow logged-in teachers/admins ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["user_type"] !== 'teacher' && $_SESSION["user_type"] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

// --- 2. Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['report_id']) || !isset($_POST['new_status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit;
}

// Include database connection
// Assuming db_connect.php is in the same directory
include 'db_connect.php'; 

$report_id = (int)$_POST['report_id'];
$new_status = $_POST['new_status'];

$valid_statuses = ['Reported', 'Reworking', 'Resolved'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

// --- 3. Database Transaction ---
// Use a transaction to ensure both report and computer status updates succeed or fail together.
$conn->begin_transaction();
$success = false;
$computer_id = 0;

try {
    // 3a. Get the computer ID associated with the report
    $stmt_report = $conn->prepare("SELECT computer_id FROM reports WHERE id = ?");
    $stmt_report->bind_param("i", $report_id);
    $stmt_report->execute();
    $result = $stmt_report->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Report not found.");
    }
    $computer_id = $result->fetch_assoc()['computer_id'];
    $stmt_report->close();
    
    // 3b. Update the status of the specific report
    $stmt_update_report = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt_update_report->bind_param("si", $new_status, $report_id);
    $stmt_update_report->execute();
    $stmt_update_report->close();

    // 3c. Update the computer's overall status based on the new report status
    // If the report is resolved, the computer status should be 'OK'. Otherwise, it matches the report status.
    $computer_status = ($new_status === 'Resolved') ? 'OK' : $new_status;

    $stmt_update_computer = $conn->prepare("UPDATE computers SET status = ? WHERE id = ?");
    $stmt_update_computer->bind_param("si", $computer_status, $computer_id);
    $stmt_update_computer->execute();
    $stmt_update_computer->close();

    // If both updates worked, commit the changes
    $conn->commit();
    $success = true;

} catch (Exception $e) {
    // If anything failed, roll back the transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed due to a server error.']);
    exit;
}

// --- 4. Success Response ---
if ($success) {
    echo json_encode(['success' => true, 'new_status' => $new_status]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed unexpectedly.']);
}
?>