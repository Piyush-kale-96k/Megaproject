<?php
// update_report_status.php
// This endpoint handles AJAX requests: updating status OR adding an internal note.
// CRITICAL FIX: Ensures only clean JSON is returned to prevent AJAX errors.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON immediately
header('Content-Type: application/json');

// --- 1. Security Check ---
$user_id = $_SESSION['user_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? '';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($user_type !== 'teacher' && $user_type !== 'technician')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

// --- 2. Input Validation (Moved include here, after header and security check) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

include 'db_connect.php'; 

$new_status = $_POST['new_status'] ?? '';
$note_description = trim($_POST['note_description'] ?? '');
$is_internal_note = isset($_POST['is_internal_note']); 

$valid_statuses = ['Reported', 'Reworking', 'Resolved', 'OK'];

// Check status if it was sent in the request (it might not be for internal note)
if (!empty($new_status) && !in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit;
}

$conn->begin_transaction();
$response_data = ['success' => false, 'message' => 'Update failed unexpectedly.'];

try {
    
    // --- SCENARIO A: Status Update (Processing an existing report) ---
    // Triggered when a staff member updates an actively Reported/Reworking PC status via modal.
    if (isset($_POST['report_id'])) {
        $report_id = (int)$_POST['report_id'];
        
        // 1. Get associated computer ID
        $stmt_report = $conn->prepare("SELECT computer_id FROM reports WHERE id = ?");
        $stmt_report->bind_param("i", $report_id);
        $stmt_report->execute();
        $result = $stmt_report->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Report not found.");
        }
        $computer_id = $result->fetch_assoc()['computer_id'];
        $stmt_report->close();
        
        // 2. Authorization Check (Branch/Manager Restriction)
        $auth_stmt = $conn->prepare("SELECT l.id FROM labs l JOIN computers c ON l.id = c.lab_id WHERE c.id = ? AND l.manager_id = ?");
        $auth_stmt->bind_param("ii", $computer_id, $user_id);
        $auth_stmt->execute();
        if ($auth_stmt->get_result()->num_rows == 0) {
            throw new Exception("Unauthorized: You do not manage this lab.");
        }
        $auth_stmt->close();
        
        // 3. Update the report status (The existing report)
        $stmt_update_report = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
        $stmt_update_report->bind_param("si", $new_status, $report_id);
        $stmt_update_report->execute();
        $stmt_update_report->close();
        
        // 4. Insert a note if provided (as a separate report entry for history)
        if (!empty($note_description)) {
            $note_category = "Maintenance Note";
            $note_status = $new_status; 
            
            $stmt_insert_note = $conn->prepare("INSERT INTO reports (user_id, computer_id, category, description, status) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert_note->bind_param("iiss", $user_id, $computer_id, $note_category, $note_description, $note_status);
            $stmt_insert_note->execute();
            $stmt_insert_note->close();
        }

        // 5. Update the computer's overall status 
        $computer_status = ($new_status === 'Resolved') ? 'OK' : $new_status;

        $stmt_update_computer = $conn->prepare("UPDATE computers SET status = ? WHERE id = ?");
        $stmt_update_computer->bind_param("si", $computer_status, $computer_id);
        $stmt_update_computer->execute();
        $stmt_update_computer->close();

        $response_data = ['success' => true, 'new_status' => $new_status];


    // --- SCENARIO B: Internal Note Submission (Adding note to an OK PC) ---
    // Triggered when a staff member adds a note to a working PC to log maintenance/inspection.
    } elseif ($is_internal_note && isset($_POST['computer_id'])) {
        $computer_id = (int)$_POST['computer_id'];
        
        if (empty($note_description)) {
            throw new Exception("Note description cannot be empty.");
        }
        
        // 1. Authorization Check
        $auth_stmt = $conn->prepare("SELECT l.id FROM labs l JOIN computers c ON l.id = c.lab_id WHERE c.id = ? AND l.manager_id = ?");
        $auth_stmt->bind_param("ii", $computer_id, $user_id);
        $auth_stmt->execute();
        if ($auth_stmt->get_result()->num_rows == 0) {
            throw new Exception("Unauthorized: You do not manage this lab to add internal notes.");
        }
        $auth_stmt->close();

        // 2. Insert the internal note as a Resolved report (so it doesn't show up in pending reports)
        $note_category = "Internal Note";
        $note_status = "Resolved";
        
        $stmt_insert_note = $conn->prepare("INSERT INTO reports (user_id, computer_id, category, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert_note->bind_param("iiss", $user_id, $computer_id, $note_category, $note_description, $note_status);
        $stmt_insert_note->execute();
        $stmt_insert_note->close();

        $response_data = ['success' => true, 'new_status' => 'OK (Note Saved)'];
        
    } else {
         throw new Exception("Invalid parameters for update or note submission.");
    }
    
    // Commit the transaction only if no exceptions were thrown
    $conn->commit();

} catch (Exception $e) {
    // Rollback changes on error
    $conn->rollback();
    http_response_code(500);
    $response_data = ['success' => false, 'message' => $e->getMessage()];
    
}

// Final output of JSON and termination of script
echo json_encode($response_data);
$conn->close();
exit;
?>