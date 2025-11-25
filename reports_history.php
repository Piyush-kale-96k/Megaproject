<?php
// reports_history.php (Content Fragment)
// Displays a table of all reports, filterable by date range (1 day, 7 days, 1 month, 60 days).

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Security check: only allow staff (Teacher/Technician)
$user_type = $_SESSION['user_type'] ?? 'student';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($user_type === 'student')) {
    echo "<div class='p-4 text-red-600 bg-red-100 rounded-lg'>Permission denied. Only staff can view full report history.</div>";
    return;
}

// Get filter parameters
$filter_period = $_GET['period'] ?? '1month'; 
$reports = [];

// Determine the starting date for the SQL query
switch ($filter_period) {
    case '1day':
        $start_date = date('Y-m-d H:i:s', strtotime('-1 day'));
        $title_suffix = " (Last 24 Hours)";
        break;
    case '7days':
        $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        $title_suffix = " (Last 7 Days)";
        break;
    case '1month':
        $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $title_suffix = " (Last 30 Days)";
        break;
    case '60days': // NEW 60 DAYS LOGIC
        $start_date = date('Y-m-d H:i:s', strtotime('-60 days'));
        $title_suffix = " (Last 60 Days)";
        break;
    default:
        $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $title_suffix = " (Last 30 Days)";
        $filter_period = '1month'; // Default back to 1month if invalid
        break;
}

// Query to get all reports within the specified time frame
$sql = "SELECT 
            r.id AS report_id,
            r.status AS report_status,
            r.description,
            r.category,
            r.created_at,
            c.pc_number,
            l.name AS lab_name,
            u.name AS user_name
        FROM reports r
        JOIN users u ON r.user_id = u.id
        JOIN computers c ON r.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        WHERE r.created_at >= ?
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $start_date);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $reports = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
$stmt->close();
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="max-w-full mx-auto bg-white p-6 md:p-8 rounded-xl shadow-xl border border-gray-100">
        
        <header class="mb-8 border-b pb-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Historical Reports <?php echo $title_suffix; ?></h1>
            <p class="text-gray-600 mt-1">Review all logged faults and their resolution status by date.</p>
        </header>
        
        <!-- Filter Form -->
        <form action="?page=reports_history" method="GET" class="mb-8 p-5 bg-gray-50 rounded-xl border border-gray-200 shadow-inner flex flex-col sm:flex-row items-end sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
            <input type="hidden" name="page" value="reports_history">
            
            <div class="w-full sm:w-auto flex-grow">
                <label for="period" class="block text-sm font-medium text-gray-700">Select Time Period</label>
                <select id="period" name="period" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm p-2.5 focus:ring-slate-500 focus:border-slate-500 transition duration-150">
                    <option value="1day" <?php if ($filter_period === '1day') echo 'selected'; ?>>Last 24 Hours</option>
                    <option value="7days" <?php if ($filter_period === '7days') echo 'selected'; ?>>Last 7 Days</option>
                    <option value="1month" <?php if ($filter_period === '1month') echo 'selected'; ?>>Last 30 Days</option>
                    <option value="60days" <?php if ($filter_period === '60days') echo 'selected'; ?>>Last 60 Days</option>
                </select>
            </div>
            
            <div class="w-full sm:w-auto">
                <button type="submit" class="w-full justify-center py-2.5 px-6 border border-transparent rounded-lg shadow-md text-sm font-semibold text-white bg-slate-700 hover:bg-slate-800 transition-colors duration-200">
                    Apply Filter
                </button>
            </div>
        </form>

        <!-- Reports Table -->
        <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC / Lab</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Type / Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-500 bg-white">
                                No reports found for the selected period.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($report['created_at'])); ?> <br>
                                    <span class="text-xs text-gray-400"><?php echo date('H:i A', strtotime($report['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($report['lab_name']) . ' - PC ' . htmlspecialchars($report['pc_number']); ?></div>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-xs font-medium text-gray-700"><?php echo htmlspecialchars($report['category']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($report['description']); ?>"><?php echo htmlspecialchars($report['description']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($report['user_name']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full 
                                        <?php 
                                            if ($report['report_status'] == 'Reported') echo 'bg-red-200 text-red-900';
                                            elseif ($report['report_status'] == 'Reworking') echo 'bg-yellow-200 text-yellow-900';
                                            else echo 'bg-green-200 text-green-900';
                                        ?>">
                                        <?php echo htmlspecialchars($report['report_status']); ?>
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