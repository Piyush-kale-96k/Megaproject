<?php
// report.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// FIX: Use include_once for stability
include_once 'db_connect.php';

$message = "";
$messageType = "";
$labs = [];

// CRITICAL: Get user's branch from the session
$user_branch = $_SESSION['branch'] ?? 'Unknown';

// Fetch Labs
try {
    // FIX: Filter labs query by the user's branch
    $labs_query = "SELECT id, name FROM labs WHERE branch = ? ORDER BY name ASC";
    $stmt_labs = $conn->prepare($labs_query);
    $stmt_labs->bind_param("s", $user_branch);
    $stmt_labs->execute();
    $labs_result = $stmt_labs->get_result();
    
    if ($labs_result) {
        $labs = mysqli_fetch_all($labs_result, MYSQLI_ASSOC);
    }
    $stmt_labs->close();
} catch (Exception $e) {
    $message = "Error fetching lab data: " . $e->getMessage();
    $messageType = "error";
}

// Handle Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST["lab"]) || empty($_POST["pc-number"]) || empty($_POST["issue-category"]) || empty($_POST["fault-description"])) {
        $message = "Please fill out all fields.";
        $messageType = "error";
    } else {
        $lab_id = (int)$_POST["lab"];
        $pc_number = (int)$_POST["pc-number"];
        $category = htmlspecialchars($_POST["issue-category"]);
        $description = htmlspecialchars($_POST["fault-description"]);
        $user_id = $_SESSION['user_id'];

        // Security Check: Verify that the selected lab belongs to the user's branch
        $check_branch_stmt = $conn->prepare("SELECT COUNT(*) FROM labs WHERE id = ? AND branch = ?");
        $check_branch_stmt->bind_param("is", $lab_id, $user_branch);
        $check_branch_stmt->execute();
        $is_authorized = $check_branch_stmt->get_result()->fetch_row()[0] > 0;
        $check_branch_stmt->close();
        
        if (!$is_authorized) {
            $message = "Security Error: Selected lab does not belong to your branch.";
            $messageType = "error";
        } else {
            // Find the computer ID
            $find_pc = $conn->prepare("SELECT id FROM computers WHERE lab_id = ? AND pc_number = ?");
            $find_pc->bind_param("ii", $lab_id, $pc_number);
            $find_pc->execute();
            $res = $find_pc->get_result();

            if ($res->num_rows > 0) {
                $pc = $res->fetch_assoc();
                $computer_id = $pc['id'];

                $conn->begin_transaction();
                try {
                    $stmt1 = $conn->prepare("INSERT INTO reports (user_id, computer_id, category, description, status) VALUES (?, ?, ?, ?, 'Reported')");
                    $stmt1->bind_param("iiss", $user_id, $computer_id, $category, $description);
                    $stmt1->execute();

                    $stmt2 = $conn->prepare("UPDATE computers SET status = 'Reported' WHERE id = ?");
                    $stmt2->bind_param("i", $computer_id);
                    $stmt2->execute();
                    
                    $conn->commit();
                    $message = "Fault report submitted successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Database error. Failed to submit: " . $e->getMessage();
                    $messageType = "error";
                }
            } else {
                $message = "Error: PC Number $pc_number does not exist in the selected lab.";
                $messageType = "error";
            }
        }
    }
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 sm:p-8 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold text-gray-900">Report a Fault</h2>
        <!-- <p class="mt-1 text-sm text-gray-500">
            Fill out the form below to report an issue with a computer in the **<?php echo htmlspecialchars($user_branch); ?>** Branch labs.
        </p> -->

        <?php if ($message): ?>
            <div class="mt-4 p-4 rounded <?php echo $messageType == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="?page=report" method="post" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lab</label>
                    <select name="lab" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                        <option value="" disabled selected>Select a lab (<?php echo htmlspecialchars($user_branch); ?>)</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?php echo $lab['id']; ?>"><?php echo htmlspecialchars($lab['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">PC Number</label>
                    <input type="number" name="pc-number" placeholder="e.g., 5" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Issue Category</label>
                <select name="issue-category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
                    <option value="" disabled selected>Select a category</option>
                    <option>Hardware</option>
                    <option>Software</option>
                    <option>Network</option>
                    <option>Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Fault Description</label>
                <textarea name="fault-description" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border resize-none" placeholder="Describe the issue..." required></textarea>
            </div>

            <button type="submit" class="w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-700 hover:bg-slate-800 transition-colors">
                Submit Report
            </button>
        </form>
    </div>
</div>