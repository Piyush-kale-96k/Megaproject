<?php
// labs_view.php (Content Fragment)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// FIX: Use include_once for stability
include_once 'db_connect.php';

$labs_data = [];
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'student';
$user_branch = $_SESSION['branch'] ?? 'Unknown'; // NEW: Get user branch
$is_teacher = ($user_type === 'teacher');
$is_staff = $is_teacher || ($user_type === 'technician');

// Check for and display status messages from the redirect
$status_message = $_SESSION['status_message'] ?? '';
$status_type = $_SESSION['status_type'] ?? '';
unset($_SESSION['status_message']); 
unset($_SESSION['status_type']); 


// Fetch all labs, their computers, manager info, and the latest unresolved report
// CRITICAL FIX: Add WHERE l.branch = ? to filter data by the logged-in user's branch.
$sql = "SELECT 
            l.id AS lab_id, 
            l.name AS lab_name, 
            l.manager_id,
            m.name AS manager_name,
            c.id AS computer_id, 
            c.pc_number, 
            c.status,
            r.id AS report_id, 
            r.description AS report_description,
            u.name AS reporter_name,
            r.created_at AS report_date,
            r.status AS report_current_status
        FROM labs l 
        LEFT JOIN computers c ON l.id = c.lab_id 
        LEFT JOIN users m ON l.manager_id = m.id -- Manager's info
        LEFT JOIN reports r ON c.id = r.computer_id AND r.id = (
            -- Subquery to find the ID of the single latest unresolved report for this computer
            SELECT MAX(r2.id)
            FROM reports r2
            WHERE r2.computer_id = c.id
            AND r2.status != 'Resolved'
        )
        LEFT JOIN users u ON r.user_id = u.id -- Reporter's info
        WHERE l.branch = ?
        ORDER BY l.name, c.pc_number ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_branch); // NEW: Bind the user's branch
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $lab_id = $row['lab_id'];
        if (!isset($labs_data[$lab_id])) {
            $labs_data[$lab_id] = [
                'id' => $row['lab_id'],
                'name' => $row['lab_name'],
                'manager_name' => $row['manager_name'] ?? 'Unassigned', 
                'manager_id' => $row['manager_id'] ?? 0,
                'computers' => []
            ];
        }
        if ($row['computer_id'] !== null) {
             $labs_data[$lab_id]['computers'][] = [
                'computer_id' => $row['computer_id'],
                'report_id' => $row['report_id'],
                'pc_number' => $row['pc_number'],
                'status' => $row['status'],
                'report_description' => $row['report_description'],
                'reporter_name' => $row['reporter_name'],
                'report_date' => $row['report_date'],
                'report_current_status' => $row['report_current_status']
            ];
        }
    }
}
?>

<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Lab Status Overview</h1>
        <p class="text-gray-600 mt-1">Showing only labs assigned to the **<?php echo htmlspecialchars($user_branch); ?>** Branch.</p>
    </header>

    <!-- Global Status Message -->
    <?php if ($status_message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo ($status_type === 'success') ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'; ?>">
            <?php echo htmlspecialchars($status_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($labs_data)): ?>
        <div class="bg-white p-8 rounded-lg shadow-sm text-center text-gray-500">
            <p>No labs have been set up yet or assigned to the **<?php echo htmlspecialchars($user_branch); ?>** Branch.</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($labs_data as $lab): ?>
                <?php
                    // Determine if the current staff user manages this lab
                    $user_manages_lab = ($is_staff && $lab['manager_id'] == $user_id);
                ?>
                <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6">
                    <!-- Lab Header (Name + Manager + Edit Button) -->
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($lab['name']); ?></h2>
                            <p class="text-sm text-gray-500">Manager: 
                                <span class="font-medium text-slate-700"><?php echo htmlspecialchars($lab['manager_name']); ?></span>
                            </p>
                        </div>
                        <?php if ($is_teacher): // Only Teachers can edit lab structure ?>
                            <a href="?page=edit_lab&id=<?php echo $lab['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
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
                                    
                                    // Pass manager status to JS for modal button logic
                                    $can_update_status = $user_manages_lab ? '1' : '0';
                                ?>
                                <!-- Use data attributes to pass info to the modal -->
                                <div class="text-center p-3 rounded-lg border <?php echo $status_color; ?> cursor-pointer hover:shadow-md transition-shadow pc-box"
                                     data-lab-name="<?php echo htmlspecialchars($lab['name']); ?>"
                                     data-pc-number="<?php echo htmlspecialchars($computer['pc_number']); ?>"
                                     data-status="<?php echo htmlspecialchars($computer['status']); ?>"
                                     data-description="<?php echo htmlspecialchars($computer['report_description'] ?? 'N/A'); ?>"
                                     data-reporter="<?php echo htmlspecialchars($computer['reporter_name'] ?? 'N/A'); ?>"
                                     data-date="<?php echo isset($computer['report_date']) ? date('M d, Y', strtotime($computer['report_date'])) : 'N/A'; ?>"
                                     data-computer-id="<?php echo htmlspecialchars($computer['computer_id'] ?? '0'); ?>"
                                     data-report-id="<?php echo htmlspecialchars($computer['report_id'] ?? '0'); ?>"
                                     data-is-staff="<?php echo $is_staff ? '1' : '0'; ?>"
                                     data-can-update="<?php echo $can_update_status; ?>"
                                     data-report-current-status="<?php echo htmlspecialchars($computer['report_current_status'] ?? 'N/A'); ?>">
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
                const { 
                    labName, pcNumber, status, description, reporter, date, 
                    computerId, reportId, reportCurrentStatus, canUpdate, isStaff 
                } = box.dataset;
                
                const isReported = status !== 'OK';
                const canUserUpdateStatus = canUpdate === '1'; 
                const isUserStaff = isStaff === '1';

                modalTitle.textContent = `${labName} - PC ${pcNumber}`;
                modalActions.innerHTML = ''; 
                modalBody.innerHTML = '';

                // --- Build Modal Body Content ---
                let bodyHtml = '';
                let manageFormHtml = '';
                
                if (!isReported) {
                    bodyHtml = `
                        <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-center">
                            <p class="font-medium text-green-800">This computer is working correctly.</p>
                        </div>
                    `;
                    
                    // Form for staff to add notes (sends to update_report_status.php via POST)
                    if (isUserStaff) {
                        // CRITICAL FIX: Ensure computer_id is always passed
                        manageFormHtml = `
                            <div class="mt-4 p-3 border border-gray-200 rounded-lg bg-gray-50">
                                <form id="add-note-form" action="update_report_status.php" method="POST" class="space-y-3">
                                    <input type="hidden" name="computer_id" value="${computerId}">
                                    <input type="hidden" name="new_status" value="OK">
                                    <input type="hidden" name="is_internal_note" value="1">
                                    <label class="block text-sm font-medium text-gray-700">Add Internal Note (for Maintenance Log)</label>
                                    <textarea name="note_description" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm text-sm p-2" placeholder="e.g., Checked wiring, needs RAM upgrade next month..." required></textarea>
                                    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-700 hover:bg-slate-800">
                                        Save Note
                                    </button>
                                </form>
                            </div>
                        `;
                    }


                } else {
                    bodyHtml = `
                        <div class="text-sm">
                            <p><strong class="w-24 inline-block">Status:</strong> <span class="font-semibold text-red-700">${status}</span></p>
                            <p><strong class="w-24 inline-block">Reported By:</strong> ${reporter}</p>
                            <p><strong class="w-24 inline-block">Report Date:</strong> ${date}</p>
                            <p class="mt-2 pt-2 border-t"><strong class="block mb-1">Description:</strong></p>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-md">${description}</p>
                        </div>
                    `;
                    
                    // --- Build Staff Management Form if reported and user can update ---
                    if (canUserUpdateStatus) {
                        // CRITICAL FIX: Ensure report_id and computer_id are both passed in the form
                        manageFormHtml = `
                            <div class="mt-4 p-3 border border-gray-200 rounded-lg bg-gray-50">
                                <form id="update-report-form" action="update_report_status.php" method="POST" class="space-y-3">
                                    <input type="hidden" name="report_id" value="${reportId}">
                                    <input type="hidden" name="computer_id" value="${computerId}"> 
                                    <label class="block text-sm font-medium text-gray-700">Update Report Status</label>
                                    <select name="new_status" class="block w-full border-gray-300 rounded-md shadow-sm text-sm p-2 focus:ring-blue-500">
                                        <option value="Reported" ${reportCurrentStatus === 'Reported' ? 'selected' : ''}>Reported (Needs Review)</option>
                                        <option value="Reworking" ${reportCurrentStatus === 'Reworking' ? 'selected' : ''}>Reworking (In Progress)</option>
                                        <option value="Resolved" ${reportCurrentStatus === 'Resolved' ? 'selected' : ''}>Resolved (Fixed)</option>
                                    </select>
                                    
                                    <label class="block text-sm font-medium text-gray-700">Add Resolution Note (Optional)</label>
                                    <textarea name="note_description" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm text-sm p-2" placeholder="e.g., Replaced hard drive, installed Windows updates..."></textarea>

                                    <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        `;
                    } else if (isUserStaff) {
                        // Staff can see the report but cannot update it (not assigned manager)
                        manageFormHtml = `
                            <div class="mt-4 p-3 border border-yellow-300 rounded-lg bg-yellow-50 text-center text-sm text-yellow-800 font-medium">
                                You are not the manager assigned to this lab, thus you cannot update its status here.
                            </div>
                        `;
                    }
                }
                
                modalBody.innerHTML = bodyHtml + manageFormHtml;

                // --- Build Modal Actions (History and Close Button) ---
                let actionsHtml = '';

                // History Link (Visible to Staff) - FIX: Use template literal to inject computerId
                if (isUserStaff) {
                    actionsHtml += `<a href="?page=pc_history&computer_id=${computerId}" class="px-4 py-2 bg-slate-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 block mb-2">View Report History</a>`;
                }

                actionsHtml += `<button id="close-modal-btn" class="px-4 py-2 bg-slate-700 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500">Close</button>`;
                
                modalActions.innerHTML = actionsHtml;

                // Re-attach close listener
                document.getElementById('close-modal-btn').addEventListener('click', hideModal);
                
                showModal();
            });
        });
        
        // Listeners for closing modal (outside click)
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                hideModal();
            }
        });
    });
</script>