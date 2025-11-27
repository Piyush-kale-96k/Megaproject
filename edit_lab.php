<?php
// edit_lab.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Only Teacher can manage lab structure
$user_type = $_SESSION["user_type"] ?? '';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $user_type !== 'teacher') {
    echo "<script>window.location.href='homepage.php?page=home';</script>";
    exit;
}

include_once 'db_connect.php';

$message = "";
$messageType = "";
$lab_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_id === 0) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Invalid Lab ID.</div>";
    return;
}

// --- Fetch all staff (Teachers and Technicians) for the dropdown ---
$staff_members = [];
$staff_query = "SELECT id, name, user_type FROM users WHERE user_type IN ('teacher', 'technician') ORDER BY name ASC";
$staff_result = mysqli_query($conn, $staff_query);
if ($staff_result) {
    $staff_members = mysqli_fetch_all($staff_result, MYSQLI_ASSOC);
}


// --- Handle Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lab'])) {
    $new_name = trim($_POST['lab_name']);
    $new_total_pcs = (int)$_POST['total_pcs'];
    $current_total = (int)$_POST['current_total'];
    $manager_id = filter_var($_POST['manager_id'], FILTER_VALIDATE_INT);

    if (empty($new_name) || $new_total_pcs <= 0 || $manager_id === false) {
        $message = "Invalid input. Name and Manager must be set, and PC count must be positive.";
        $messageType = "error";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update Name and Manager ID
            $stmt = $conn->prepare("UPDATE labs SET name = ?, manager_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $new_name, $manager_id, $lab_id);
            $stmt->execute();
            $stmt->close();

            // 2. Adjust PCs
            if ($new_total_pcs > $current_total) {
                // ADD PCs (Start from current_total + 1)
                $stmt_add = $conn->prepare("INSERT INTO computers (lab_id, pc_number, status) VALUES (?, ?, 'OK')");
                for ($i = $current_total + 1; $i <= $new_total_pcs; $i++) {
                    $stmt_add->bind_param("ii", $lab_id, $i);
                    $stmt_add->execute();
                }
                $stmt_add->close();
            } elseif ($new_total_pcs < $current_total) {
                // REMOVE PCs (Delete PCs where pc_number > new_total)
                $stmt_del = $conn->prepare("DELETE FROM computers WHERE lab_id = ? AND pc_number > ?");
                $stmt_del->bind_param("ii", $lab_id, $new_total_pcs);
                $stmt_del->execute();
                $stmt_del->close();
            }

            $conn->commit();
            $message = "Lab updated successfully!";
            $messageType = "success";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $message = "Cannot decrease PC count because some PCs have active reports attached. Resolve or delete the reports first.";
            } else {
                $message = "Database Error: Could not process update.";
            }
            $messageType = "error";
        }
    }
}

// Fetch Current Lab Data
$stmt = $conn->prepare("SELECT l.name, l.manager_id, COUNT(c.id) as pc_count FROM labs l LEFT JOIN computers c ON l.id = c.lab_id WHERE l.id = ? GROUP BY l.id, l.manager_id, l.name");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$result = $stmt->get_result();
$lab = $result->fetch_assoc();
$stmt->close();

if (!$lab) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Lab not found.</div>";
    return;
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="max-w-xl mx-auto bg-white p-6 rounded-xl shadow-xl border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Edit Lab: <?php echo htmlspecialchars($lab['name']); ?></h2>
            <a href="?page=manage_labs" class="text-sm text-blue-600 hover:underline inline-flex items-center">&larr; Back to Labs</a>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo ($messageType === 'success') ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="?page=edit_lab&id=<?php echo $lab_id; ?>" method="POST" class="space-y-6">
            <input type="hidden" name="current_total" value="<?php echo $lab['pc_count']; ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Lab Name</label>
                <input type="text" name="lab_name" value="<?php echo htmlspecialchars($lab['name']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
            </div>
            
            <!-- Manager Dropdown -->
            <div>
                <label for="manager_id" class="block text-sm font-medium text-gray-700">Assign Manager / Branch Lead</label>
                <select id="manager_id" name="manager_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500" required>
                    <option value="" disabled>Select Staff Member</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php if ($lab['manager_id'] == $staff['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($staff['name']); ?> (<?php echo ucfirst($staff['user_type']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Total Number of PCs (Currently: <?php echo $lab['pc_count']; ?>)</label>
                <p class="text-xs text-gray-500 mb-2">Increase to add PCs. Decrease to remove (Warning: May fail if PCs have active reports).</p>
                <input type="number" name="total_pcs" value="<?php echo htmlspecialchars($lab['pc_count']); ?>" min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 border" required>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="update_lab" class="bg-slate-700 text-white px-4 py-2 rounded-md hover:bg-slate-800 transition">
                    Update Lab
                </button>
            </div>
        </form>
    </div>
</div>