<?php
// home.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// FIX: Use include_once for stability
include_once 'db_connect.php';

// Ensure user is logged in before proceeding
if (!isset($_SESSION['user_id'])) {
    echo "Please login to view this page.";
    return;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'student';
// CRITICAL: Get user branch
$user_branch = $_SESSION['branch'] ?? 'Unknown'; 
$is_staff = ($user_type === 'teacher' || $user_type === 'technician');


// --- FETCH LIVE DATA FROM DATABASE ---

// 1. Calculate PC Status Counts (Filtered by Branch)
$okPcsCount = 0;
$reworkingPcsCount = 0;
$reportedPcsCount = 0;

// CRITICAL FIX: Filter status query by lab branch
$status_query = "SELECT 
                    c.status, 
                    COUNT(c.id) as count 
                 FROM computers c
                 JOIN labs l ON c.lab_id = l.id
                 WHERE l.branch = ?
                 GROUP BY c.status";

$stmt_status = $conn->prepare($status_query);
$stmt_status->bind_param("s", $user_branch);
$stmt_status->execute();
$status_result = $stmt_status->get_result();

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
$stmt_status->close();

// Calculate total PCs in the branch for context
$total_pcs_branch = $okPcsCount + $reworkingPcsCount + $reportedPcsCount;


// 2. Fetch reports based on user type and assignment (Filtered by Branch)
$userReports = [];
$reports_query_params = [];
$reports_query_types = "";

if ($user_type === 'student') {
    // Students only see their own reports (filtered by user_id)
    $report_title = "My Submitted Reports (Across " . htmlspecialchars($user_branch) . " Branch)";
    $reports_query = "
        SELECT 
            r.category,
            c.pc_number, 
            l.name AS lab_name, 
            r.description, 
            r.created_at, 
            r.status,
            '' AS reporter_name
        FROM reports r
        JOIN computers c ON r.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        WHERE r.user_id = ? AND l.branch = ?
        ORDER BY r.created_at DESC";
    $reports_query_params = [$user_id, $user_branch];
    $reports_query_types = "is";

} else {
    // Teachers/Technicians see pending reports ONLY from labs assigned to their branch
    $report_title = "Pending Reports for Labs in Your Branch (" . htmlspecialchars($user_branch) . ")";
    
    // Staff should see ALL pending reports in their branch, regardless of manager_id,
    // as management duties can overlap, but ownership must be strictly branch-based.
    $reports_query = "
        SELECT 
            r.category,
            c.pc_number, 
            l.name AS lab_name, 
            r.description, 
            r.created_at, 
            r.status,
            u.name AS reporter_name
        FROM reports r
        JOIN computers c ON r.computer_id = c.id
        JOIN labs l ON c.lab_id = l.id
        JOIN users u ON r.user_id = u.id
        WHERE l.branch = ? AND r.status != 'Resolved'
        ORDER BY FIELD(r.status, 'Reported', 'Reworking'), r.created_at DESC";
    $reports_query_params[] = $user_branch;
    $reports_query_types = "s";
}

// Execute the final report query
$stmt = $conn->prepare($reports_query);

if (!empty($reports_query_params)) {
    // Note: We use call_user_func_array for binding when parameters are dynamic, 
    // but since PHP 5.6+ and with the fixed types/count, we can use $stmt->bind_param(...$reports_query_params);
    $stmt->bind_param($reports_query_types, ...$reports_query_params);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $userReports = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Overview</h1>
        <p class="text-gray-600 mt-1">Status of all **<?php echo htmlspecialchars($user_branch); ?>** Branch PCs (Total: <?php echo $total_pcs_branch; ?>)</p>
    </header>
    
    <!-- Status Summary Boxes -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-white border border-green-200 rounded-xl shadow-md p-6 flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Working PCs</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $okPcsCount; ?></p>
            </div>
        </div>

        <div class="bg-white border border-yellow-200 rounded-xl shadow-md p-6 flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Reworking PCs</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $reworkingPcsCount; ?></p>
            </div>
        </div>
        
        <div class="bg-white border border-red-200 rounded-xl shadow-md p-6 flex items-center">
            <div class="bg-red-100 p-3 rounded-full mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Reported PCs</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $reportedPcsCount; ?></p>
            </div>
        </div>
    </div>

    <!-- Reports Section: Card List -->
    <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-2"><?php echo $report_title; ?></h2>
        
        <?php if (empty($userReports)): ?>
            <div class="text-center py-8 text-gray-500">
                <?php echo $is_staff ? 'No pending reports found for your assigned branch labs.' : 'You have not submitted any reports yet.'; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($userReports as $report): ?>
                    <?php
                        $status = htmlspecialchars($report['status']);
                        $status_color_bg = 'bg-gray-50';
                        $status_color_border = 'border-gray-200';
                        $status_badge_bg = 'bg-gray-200 text-gray-800';

                        if ($status === 'Reported') {
                            $status_color_bg = 'bg-red-50';
                            $status_color_border = 'border-red-300';
                            $status_badge_bg = 'bg-red-200 text-red-800';
                        } elseif ($status === 'Reworking') {
                            $status_color_bg = 'bg-yellow-50';
                            $status_color_border = 'border-yellow-300';
                            $status_badge_bg = 'bg-yellow-200 text-yellow-800';
                        } elseif ($status === 'Resolved') {
                            $status_color_bg = 'bg-green-50';
                            $status_color_border = 'border-green-300';
                            $status_badge_bg = 'bg-green-200 text-green-800';
                        }
                    ?>
                    <div class="p-4 border rounded-xl shadow-sm <?php echo $status_color_bg; ?> <?php echo $status_color_border; ?>">
                        
                        <!-- Top Row: PC Info and Status Badge -->
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-lg text-gray-900">
                                <?php echo htmlspecialchars($report['lab_name']) . ' - PC ' . htmlspecialchars($report['pc_number']); ?>
                            </h3>
                            <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo $status_badge_bg; ?>">
                                <?php echo $status; ?>
                            </span>
                        </div>
                        
                        <!-- Description -->
                        <p class="text-sm text-gray-700 mb-3 leading-relaxed">
                            <?php echo htmlspecialchars($report['description']); ?>
                        </p>
                        
                        <!-- Footer Row: Reporter and Date -->
                        <div class="text-xs text-gray-500 border-t border-gray-300 pt-3 mt-3 flex justify-between flex-wrap">
                            <?php if ($is_staff): ?>
                                <span>Reported By: **<?php echo htmlspecialchars($report['reporter_name']); ?>**</span>
                            <?php else: ?>
                                <span>You reported this.</span>
                            <?php endif; ?>
                            <span>Date: <?php echo date('M d, Y H:i A', strtotime($report['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>