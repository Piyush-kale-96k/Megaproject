<?php
// labs_view.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security check: ensure user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

include 'db_connect.php';

$labs_data = [];
$is_teacher = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher');

// Fetch all labs, their computers, and the latest unresolved report for each computer
$sql = "SELECT 
            l.id AS lab_id, 
            l.name AS lab_name, 
            c.id AS computer_id, 
            c.pc_number, 
            c.status,
            r.id AS report_id, -- CRITICAL: Fetch the latest report ID
            r.description AS report_description,
            u.name AS reporter_name,
            r.created_at AS report_date
        FROM labs l 
        LEFT JOIN computers c ON l.id = c.lab_id 
        LEFT JOIN (
            -- This subquery finds the single latest unresolved report for each computer
            SELECT 
                id, computer_id, description, user_id, created_at,
                ROW_NUMBER() OVER(PARTITION BY computer_id ORDER BY created_at DESC) as rn
            FROM reports
            WHERE status != 'Resolved'
        ) r ON c.id = r.computer_id AND r.rn = 1
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY l.name, c.pc_number ASC";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lab_id = $row['lab_id'];
        if (!isset($labs_data[$lab_id])) {
            $labs_data[$lab_id] = [
                'id' => $row['lab_id'], // Store ID here for the link
                'name' => $row['lab_name'],
                'computers' => []
            ];
        }
        if ($row['computer_id'] !== null) {
             $labs_data[$lab_id]['computers'][] = [
                'computer_id' => $row['computer_id'], // Store computer ID
                'report_id' => $row['report_id'],     // Store report ID
                'pc_number' => $row['pc_number'],
                'status' => $row['status'],
                'report_description' => $row['report_description'],
                'reporter_name' => $row['reporter_name'],
                'report_date' => $row['report_date']
            ];
        }
    }
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Lab Status Overview</h1>
        <p class="text-gray-600 mt-1">Click on any PC to view its details.</p>
    </header>

    <?php if (empty($labs_data)): ?>
        <div class="bg-white p-8 rounded-lg shadow-sm text-center text-gray-500">
            <p>No labs have been set up yet. A teacher can add labs from the "Manage Labs" page.</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($labs_data as $lab): ?>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <!-- Lab Header (Name + Edit Button for Teachers) -->
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($lab['name']); ?></h2>
                        <?php if ($is_teacher): ?>
                            <a href="?page=edit_lab&id=<?php echo $lab['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                                <svg class="w-4 h-4 mr-1 -ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                Edit Lab
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($lab['computers'])): ?>
                        <p class="text-gray-500">This lab has no computers assigned to it yet.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
                            <?php foreach ($lab['computers'] as $computer): ?>
                                <?php
                                    $status_color = 'bg-gray-200 text-gray-800'; // Default
                                    if ($computer['status'] === 'OK') {
                                        $status_color = 'bg-green-100 text-green-800 border-green-300';
                                    } elseif ($computer['status'] === 'Reported') {
                                        $status_color = 'bg-red-100 text-red-800 border-red-300';
                                    } elseif ($computer['status'] === 'Reworking') {
                                        $status_color = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                                    }
                                ?>
                                <!-- Added pc-box class and data attributes for the modal -->
                                <div class="text-center p-3 rounded-lg border <?php echo $status_color; ?> cursor-pointer hover:shadow-md transition-shadow pc-box"
                                     data-lab-name="<?php echo htmlspecialchars($lab['name']); ?>"
                                     data-pc-number="<?php echo htmlspecialchars($computer['pc_number']); ?>"
                                     data-status="<?php echo htmlspecialchars($computer['status']); ?>"
                                     data-description="<?php echo htmlspecialchars($computer['report_description'] ?? 'N/A'); ?>"
                                     data-reporter="<?php echo htmlspecialchars($computer['reporter_name'] ?? 'N/A'); ?>"
                                     data-date="<?php echo isset($computer['report_date']) ? date('M d, Y', strtotime($computer['report_date'])) : 'N/A'; ?>"
                                     data-computer-id="<?php echo htmlspecialchars($computer['computer_id'] ?? '0'); ?>"
                                     data-report-id="<?php echo htmlspecialchars($computer['report_id'] ?? '0'); ?>"
                                     data-is-teacher="<?php echo $is_teacher ? '1' : '0'; ?>">
                                    <div class="font-bold text-lg">PC <?php echo htmlspecialchars($computer['pc_number']); ?></div>
                                    <div class="text-xs font-medium"><?php echo htmlspecialchars($computer['status']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- PC Details Modal -->
<div id="pc-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full hidden items-center justify-center z-50">
  <div class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
    <div class="text-center">
      <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">PC Details</h3>
      <div class="mt-4 px-4 py-3 text-left space-y-3" id="modal-body">
        <!-- Content will be injected by JavaScript -->
      </div>
      <div class="items-center px-4 py-3 mt-2" id="modal-actions">
        <!-- Actions (Manage Report Button and Close) will be injected here -->
      </div>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('pc-details-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');
        const modalActions = document.getElementById('modal-actions');
        const pcBoxes = document.querySelectorAll('.pc-box');
        const isTeacher = modal.dataset.isTeacher === '1'; // Get teacher status from a reference point

        const showModal = () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const hideModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        pcBoxes.forEach(box => {
            box.addEventListener('click', () => {
                const { labName, pcNumber, status, description, reporter, date, computerId, reportId, isTeacher } = box.dataset;
                const isReported = status !== 'OK';
                
                modalTitle.textContent = `${labName} - PC ${pcNumber}`;

                // --- Build Modal Body Content ---
                if (!isReported) {
                    modalBody.innerHTML = `
                        <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-center">
                            <p class="font-medium text-green-800">This computer is working correctly.</p>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = `
                        <div class="text-sm">
                            <p><strong class="w-24 inline-block">Status:</strong> <span class="font-semibold text-gray-700">${status}</span></p>
                            <p><strong class="w-24 inline-block">Reported By:</strong> ${reporter}</p>
                            <p><strong class="w-24 inline-block">Report Date:</strong> ${date}</p>
                            <p class="mt-2 pt-2 border-t"><strong class="block mb-1">Description:</strong></p>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-md">${description}</p>
                        </div>
                    `;
                }
                
                // --- Build Modal Actions (Buttons) ---
                let actionsHtml = `<button id="close-modal-btn" class="px-4 py-2 bg-slate-700 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500">Close</button>`;

                if (isTeacher === '1' && isReported) {
                    // Inject the Manage Report button for teachers
                    // This links directly to the Admin Dashboard entry for this report
                    const manageUrl = `?page=admin_dashboard&report_id=${reportId}&computer_id=${computerId}`;
                    
                    actionsHtml = `
                        <div class="flex flex-col space-y-2">
                            <a href="${manageUrl}" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                Manage/Update Report
                            </a>
                            ${actionsHtml}
                        </div>
                    `;
                }
                
                modalActions.innerHTML = actionsHtml;
                
                // Re-attach close listener since actionsHtml was overwritten
                document.getElementById('close-modal-btn').addEventListener('click', hideModal);

                showModal();
            });
        });
        
        // Listeners for closing modal (outside click and button)
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                hideModal();
            }
        });
    });
</script>