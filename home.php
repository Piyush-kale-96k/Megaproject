<?php
// home.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Ensure user is logged in before proceeding
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// --- FETCH LIVE DATA FROM DATABASE ---

// 1. Calculate PC Status Counts
$okPcsCount = 0;
$reworkingPcsCount = 0;
$reportedPcsCount = 0;

$status_query = "SELECT status, COUNT(*) as count FROM computers GROUP BY status";
$status_result = mysqli_query($conn, $status_query);
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        if ($row['status'] === 'OK') {
            $okPcsCount = $row['count'];
        } elseif ($row['status'] === 'Reworking') {
            $reworkingPcsCount = $row['count'];
        } elseif ($row['status'] === 'Reported') {
            $reportedPcsCount = $row['count'];
        }
    }
}

// 2. Fetch reports submitted by the currently logged-in user
$userReports = [];
$reports_query = "SELECT 
                    c.pc_number, 
                    l.name AS lab_name, 
                    r.description, 
                    r.created_at, 
                    r.status
                  FROM reports r
                  JOIN computers c ON r.computer_id = c.id
                  JOIN labs l ON c.lab_id = l.id
                  WHERE r.user_id = ?
                  ORDER BY r.created_at DESC";

$stmt = $conn->prepare($reports_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $userReports = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Lab Status Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">PC Lab Status Dashboard</h1>
            <p class="text-gray-600 mt-1">Welcome! Here's a live overview of the computer labs.</p>
        </header>

        <!-- Status Summary Boxes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            
            <div class="bg-white border border-green-200 rounded-lg shadow-sm p-6 flex items-center">
                <div class="bg-green-100 p-3 rounded-full mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Working PCs</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $okPcsCount; ?></p>
                </div>
            </div>

            <div class="bg-white border border-yellow-200 rounded-lg shadow-sm p-6 flex items-center">
                <div class="bg-yellow-100 p-3 rounded-full mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Reworking PCs</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $reworkingPcsCount; ?></p>
                </div>
            </div>
            
            <div class="bg-white border border-red-200 rounded-lg shadow-sm p-6 flex items-center">
                <div class="bg-red-100 p-3 rounded-full mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Reported PCs</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $reportedPcsCount; ?></p>
                </div>
            </div>
        </div>

        <!-- My Reports Section -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">My Submitted Reports</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Reported</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($userReports)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-gray-500">You have not submitted any reports yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userReports as $report): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($report['lab_name'] . ' - PC ' . $report['pc_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($report['description']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                                if ($report['status'] == 'Reported') echo 'bg-red-100 text-red-800';
                                                elseif ($report['status'] == 'Reworking') echo 'bg-yellow-100 text-yellow-800';
                                                else echo 'bg-green-100 text-green-800';
                                            ?>">
                                            <?php echo htmlspecialchars($report['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

