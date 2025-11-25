<?php
// manage_labs.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure the user is logged in and is a teacher.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'teacher') {
    echo "<script>window.location.href='homepage.php?page=home';</script>";
    exit;
}

include_once 'db_connect.php'; // Your database connection

$message = "";
$messageType = ""; // 'success' or 'error'

// --- Handle Form Submission to Add New Lab and PCs ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab'])) {
    $lab_name = trim($_POST['lab_name']);
    $pc_count = filter_var($_POST['pc_count'], FILTER_VALIDATE_INT);

    // Validation
    if (empty($lab_name)) {
        $message = "Lab name cannot be empty.";
        $messageType = "error";
    } elseif ($pc_count === false || $pc_count <= 0) {
        $message = "Please enter a valid number of PCs (must be 1 or more).";
        $messageType = "error";
    } else {
        // Use a transaction to ensure both operations succeed or fail together
        $conn->begin_transaction();
        try {
            // 1. Insert the new lab
            $stmt_lab = $conn->prepare("INSERT INTO labs (name) VALUES (?)");
            $stmt_lab->bind_param("s", $lab_name);
            $stmt_lab->execute();
            $new_lab_id = $conn->insert_id; // Get the ID of the new lab
            $stmt_lab->close();

            // 2. Insert the specified number of PCs for the new lab
            $stmt_pc = $conn->prepare("INSERT INTO computers (lab_id, pc_number, status) VALUES (?, ?, 'OK')");
            for ($i = 1; $i <= $pc_count; $i++) {
                $stmt_pc->bind_param("ii", $new_lab_id, $i);
                $stmt_pc->execute();
            }
            $stmt_pc->close();

            // If all went well, commit the transaction
            $conn->commit();
            $message = "Successfully added lab '" . htmlspecialchars($lab_name) . "' with " . $pc_count . " PCs.";
            $messageType = "success";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback(); // Revert changes if anything went wrong
            $message = "Database error: Could not add the lab. It might already exist.";
            $messageType = "error";
        }
    }
}

// --- Fetch Existing Labs and their PC counts ---
$existing_labs = [];
$sql = "SELECT l.id, l.name, COUNT(c.id) AS pc_count 
        FROM labs l 
        LEFT JOIN computers c ON l.id = c.lab_id 
        GROUP BY l.id, l.name 
        ORDER BY l.name ASC";
$result = mysqli_query($conn, $sql);
if ($result) {
    $existing_labs = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Manage Labs</h1>
        <p class="text-gray-600 mt-1">Add new labs and configure PC counts.</p>
    </header>

    <!-- Display Success/Error Messages -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo ($messageType === 'success') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add New Lab Form -->
        <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-sm h-fit">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Lab</h2>
            <form action="?page=manage_labs" method="POST" class="space-y-4">
                <div>
                    <label for="lab_name" class="block text-sm font-medium text-gray-700">Lab Name</label>
                    <input type="text" id="lab_name" name="lab_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Physics Lab" required>
                </div>
                <div>
                    <label for="pc_count" class="block text-sm font-medium text-gray-700">Number of PCs in Lab</label>
                    <input type="number" id="pc_count" name="pc_count" min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., 25" required>
                </div>
                <div>
                    <button type="submit" name="add_lab" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-700 hover:bg-slate-800">
                        Add Lab
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Labs List -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Existing Labs</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lab Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total PCs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($existing_labs)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-gray-500">No labs found. Add one using the form.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($existing_labs as $lab): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lab['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($lab['pc_count']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?page=edit_lab&id=<?php echo $lab['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>