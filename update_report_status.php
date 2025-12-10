<?php
// update_report_status.php
// ACTION: Processes status updates and internal notes using traditional POST and redirects.
// FINAL VERSION: Uses `resolution_note` column and ensures correct ID handling.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// FIX: Use include_once for stability
include_once 'db_connect.php'; 

// Function to handle redirection and exit
function redirect_and_exit($message, $type = 'success') {
    $_SESSION['status_message'] = $message;
    $_SESSION['status_type'] = $type;
    // Redirect to the Lab Overview page after processing the action
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

// Ensure IDs are safely cast to integers
$report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
// $computer_id is now treated as potentially missing, will be looked up if report_id exists
$computer_id = filter_var($_POST['computer_id'] ?? 0, FILTER_VALIDATE_INT); 
$new_status = $_POST['new_status'] ?? '';
$note_description = trim($_POST['note_description'] ?? '');
$is_internal_note = isset($_POST['is_internal_note']); 

$valid_statuses = ['Reported', 'Reworking', 'Resolved', 'OK'];

// Check status validity if it's a status update request
if ($report_id > 0 && !in_array($new_status, $valid_statuses)) {
    redirect_and_exit('Invalid status value.', 'error');
}

$conn->begin_transaction();
$message = 'Update failed unexpectedly.';
$type = 'error';

try {
    
    // --- SCENARIO A: Status Update (Processing an existing report) ---
    if ($report_id > 0) {
        
        // --- CRITICAL FIX: Look up Computer ID if missing from POST ---
        if ($computer_id === 0) {
            $stmt_pc_lookup = $conn->prepare("SELECT computer_id FROM reports WHERE id = ?");
            $stmt_pc_lookup->bind_param("i", $report_id);
            $stmt_pc_lookup->execute();
            $result_pc = $stmt_pc_lookup->get_result();
            if ($result_pc->num_rows === 1) {
                 $computer_id = $result_pc->fetch_assoc()['computer_id'];
            }
            $stmt_pc_lookup->close();
        }
        
        // Final verification check after attempting lookup
        if ($computer_id === 0) {
            throw new Exception("Missing Computer ID for report update, even after database lookup.");
        }
        // --- END CRITICAL FIX ---
        
        // 1. Authorization Check (Branch/Manager Restriction)
        $auth_stmt = $conn->prepare("SELECT l.id FROM labs l JOIN computers c ON l.id = c.lab_id WHERE c.id = ? AND l.manager_id = ?");
        $auth_stmt->bind_param("ii", $computer_id, $user_id);
        $auth_stmt->execute();
        if ($auth_stmt->get_result()->num_rows == 0) {
            throw new Exception("Unauthorized: You do not manage this lab.");
        }
        $auth_stmt->close();
        
        // 2. Update the report status AND store the resolution note in the dedicated column
        $stmt_update_report = $conn->prepare("UPDATE reports SET status = ?, resolution_note = ? WHERE id = ?");
        $stmt_update_report->bind_param("ssi", $new_status, $note_description, $report_id);
        $stmt_update_report->execute();
        $stmt_update_report->close();
        
        // 3. Update the computer's overall status 
        $computer_status = ($new_status === 'Resolved') ? 'OK' : $new_status;

        $stmt_update_computer = $conn->prepare("UPDATE computers SET status = ? WHERE id = ?");
        $stmt_update_computer->bind_param("si", $computer_status, $computer_id);
        $stmt_update_computer->execute();
        $stmt_update_computer->close();

        $message = "Status updated to " . htmlspecialchars($new_status) . " successfully! Note saved.";
        $type = 'success';


    // --- SCENARIO B: Internal Note Submission (Adding note to an OK PC) ---
    } elseif ($is_internal_note && $computer_id > 0) {
        
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

        // 2. Insert the internal note as a Resolved report.
        $note_category = "Internal Maintenance Log";
        $note_status = "Resolved";
        $main_description = "[INTERNAL NOTE: Maintenance Logged]"; 
        
        // CRITICAL: We insert the note into the 'resolution_note' column
        $stmt_insert_note = $conn->prepare("INSERT INTO reports (user_id, computer_id, category, description, resolution_note, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert_note->bind_param("iissis", $user_id, $computer_id, $note_category, $main_description, $note_description, $note_status);
        $stmt_insert_note->execute();
        $stmt_insert_note->close();

        $message = "Internal maintenance note saved successfully!";
        $type = 'success';
        
    } else {
         throw new Exception("Invalid parameters for update or note submission.");
    }
    
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $message = "Database Error (Check Logs/Table): " . $e->getMessage();
    $type = 'error';
}

$conn->close();
redirect_and_exit($message, $type);