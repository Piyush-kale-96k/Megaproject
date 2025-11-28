<?php
// update_report_status.php
// ACTION: Processes status updates and internal notes using traditional POST and redirects.
// JSON/AJAX RESPONSE HAS BEEN REMOVED TO PREVENT CORRUPTION ERRORS.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php'; 

// Function to handle redirection and exit
function redirect_and_exit($message, $type = 'success') {
    $_SESSION['status_message'] = $message;
    $_SESSION['status_type'] = $type;
    header("Location: homepage.php?page=labs_view");
    exit;
}

// --- 1. Security Check ---
$user_id = $_SESSION['user_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? '';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($user_type !== 'teacher' && $user_type !== 'technician')) {
    redirect_and_exit('Permission denied.', 'error');
}

// --- 2. Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_and_exit('Invalid request method.', 'error');
}

$new_status = $_POST['new_status'] ?? '';
$note_description = trim($_POST['note_description'] ?? '');
$is_internal_note = isset($_POST['is_internal_note']); 

$valid_statuses = ['Reported', 'Reworking', 'Resolved', 'OK'];

// Check status if it was sent in the request
if (!empty($new_status) && !in_array($new_status, $valid_statuses)) {
    redirect_and_exit('Invalid status value.', 'error');
}

$conn->begin_transaction();
$message = 'Update failed unexpectedly.';
$type = 'error';

try {
    
    // --- SCENARIO A: Status Update (Processing an existing report) ---
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
        
        // 4. Insert a note if provided (for history)
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

        $message = "Status updated to " . htmlspecialchars($new_status) . " successfully!";
        $type = 'success';


    // --- SCENARIO B: Internal Note Submission (Adding note to an OK PC) ---
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

        // 2. Insert the internal note as a Resolved report
        $note_category = "Internal Note";
        $note_status = "Resolved";
        
        $stmt_insert_note = $conn->prepare("INSERT INTO reports (user_id, computer_id, category, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert_note->bind_param("iiss", $user_id, $computer_id, $note_category, $note_description, $note_status);
        $stmt_insert_note->execute();
        $stmt_insert_note->close();

        $message = "Internal maintenance note saved successfully!";
        $type = 'success';
        
    } else {
         throw new Exception("Invalid parameters for update or note submission.");
    }
    
    // Commit the transaction only if no exceptions were thrown
    $conn->commit();

} catch (Exception $e) {
    // Rollback changes on error
    $conn->rollback();
    $message = "Database Error: " . $e->getMessage();
    $type = 'error';
    
}

$conn->close();
redirect_and_exit($message, $type);
?>