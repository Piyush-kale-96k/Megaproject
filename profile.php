<?php
ob_start(); // Start output buffering

// Start the session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check to ensure the user_id and branch are set in the session.
if (!isset($_SESSION['user_id'])) {
    die("Authentication error: User ID not found in session. Please log out and log in again.");
}

// FIX: Rely on single connection from db_connect.php
include_once 'db_connect.php'; 

// Initialize a message variable for feedback
$update_message = "";

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newName = htmlspecialchars($_POST["name"]);
    $newRole = htmlspecialchars($_POST["role"]);
    $newEmail = htmlspecialchars($_POST["email"]);
    // NEW: Capture Branch update from the form
    $newBranch = htmlspecialchars($_POST["branch"]); 
    $user_id = $_SESSION['user_id'];
    
    // FIX: Update query to include the branch column
    $update_stmt = $conn->prepare("UPDATE users SET name = ?, user_type = ?, email = ?, branch = ? WHERE id = ?");
    $db_role = strtolower($newRole);
    // Bind parameters now include the branch (sssi to ssssi)
    $update_stmt->bind_param("ssssi", $newName, $db_role, $newEmail, $newBranch, $user_id); 

    if ($update_stmt->execute()) {
        $update_message = "Profile updated successfully!";
        // Also update the session variables
        $_SESSION['name'] = $newName;
        $_SESSION['branch'] = $newBranch; // Update session branch
    } else {
        $update_message = "Error updating record: " . $conn->error;
    }
    $update_stmt->close();
}

// --- Data Fetching ---
$user_id = $_SESSION['user_id'];
// FIX: Fetch the 'branch' column
$stmt = $conn->prepare("SELECT name, email, user_type, branch, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $name = $user['name'];
    $email = $user['email'];
    $role = ucfirst($user['user_type']);
    $branch = htmlspecialchars($user['branch']); // Fetch branch data
    // Format the registration date
    $member_since = date("F Y", strtotime($user['created_at']));
} else {
    session_destroy();
    die("User not found. Please log in again.");
}
$stmt->close();

// Get the first initial for the profile picture
$initial = !empty($name) ? strtoupper(substr($name, 0, 1)) : '?';

// Define the branches for the forms (matching index.php for dropdown consistency)
$branches = [
    'CO' => 'Computer Eng.', 
    'AI' => 'AI / Data Science', 
    'ENTC' => 'Electronics Eng.', 
    'ELE' => 'Electrical Eng.', 
    'Mech' => 'Mechanical Eng.', 
    'CE' => 'Civil Eng.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Card</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .transition-all { transition: all 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <!-- Profile Card with Banner -->
    <div class="w-full max-w-2xl bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Banner Image -->
        <div class="relative">
            <img class="w-full h-40 object-cover" src="https://placehold.co/800x300/e0e7ff/e0e7ff" alt="Profile Banner">
             <!-- Profile Picture -->
            <img class="w-32 h-32 rounded-full absolute bottom-0 left-8 transform translate-y-1/2 border-4 border-white shadow-md bg-gray-300 flex items-center justify-center" src="https://placehold.co/128x128/94a3b8/ffffff?text=<?php echo $initial; ?>" alt="Profile Picture">
        </div>

        <!-- User Info -->
        <div class="pt-20 pb-8 px-8">
            <!-- User Details Section -->
            <div id="userInfoSection">
                <div class="flex justify-between items-start">
                     <div>
                        <h2 id="displayName" class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($name); ?></h2>
                        <!-- Display Role and Branch -->
                        <p id="displayRole" class="text-gray-500 mt-1">
                            <?php echo htmlspecialchars($role); ?> 
                            <?php if (!empty($branch)): ?>
                                <span class="text-sm font-semibold text-blue-600 ml-1">(<?php echo htmlspecialchars($branch); ?> Branch)</span>
                            <?php endif; ?>
                        </p>
                        <p id="displayEmail" class="text-gray-500 mt-1"><?php echo htmlspecialchars($email); ?></p>
                    </div>
                    <button id="editProfileBtn" class="inline-flex justify-center py-2 px-6 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-slate-700 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                        Edit Profile
                    </button>
                </div>
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-600">Member since: <span class="font-medium text-gray-800"><?php echo $member_since; ?></span></p>
                </div>
            </div>

            <!-- Edit Settings Form Section (Hidden by default) -->
            <div id="editSettingsSection" class="hidden">
                <h3 class="text-xl font-bold text-gray-800">Profile Settings</h3>
                
                <!-- Success Message -->
                <?php if (!empty($update_message) && strpos($update_message, 'successfully') !== false): ?>
                    <div class="mt-4 p-3 bg-green-100 text-green-800 border border-green-200 rounded-lg text-sm">
                        <?php echo $update_message; ?>
                    </div>
                <?php endif; ?>

                <form id="profileForm" action="?page=profile" method="post" class="mt-4 space-y-4">
                    <div>
                        <label for="nameInput" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" id="nameInput" name="name" value="<?php echo htmlspecialchars($name); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="roleInput" class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="roleInput" name="role" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 pl-3 pr-10 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option <?php if ($role == 'Student') echo 'selected'; ?>>Student</option>
                            <option <?php if ($role == 'Teacher') echo 'selected'; ?>>Teacher</option>
                            <option <?php if ($role == 'Technician') echo 'selected'; ?>>Technician</option>
                        </select>
                    </div>
                    
                    <!-- NEW: Branch Input -->
                    <div>
                        <label for="branchInput" class="block text-sm font-medium text-gray-700">Branch</label>
                        <select id="branchInput" name="branch" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 pl-3 pr-10 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                            <option value="" disabled>Select Branch</option>
                            <?php foreach ($branches as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php if ($key == $user['branch']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="emailInput" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="emailInput" name="email" value="<?php echo htmlspecialchars($email); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="flex justify-end space-x-3 pt-2">
                         <button type="button" id="cancelBtn" class="py-2 px-4 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-slate-700 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userInfoSection = document.getElementById('userInfoSection');
            const editSettingsSection = document.getElementById('editSettingsSection');
            const editProfileBtn = document.getElementById('editProfileBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const updateMessage = "<?php echo $update_message; ?>";

            // If an update message exists, it means a form was just submitted,
            // so we should show the edit view.
            if(updateMessage) {
                userInfoSection.classList.add('hidden');
                editSettingsSection.classList.remove('hidden');
            }
            
            const toggleViews = () => {
                userInfoSection.classList.toggle('hidden');
                editSettingsSection.classList.toggle('hidden');
            };

            editProfileBtn.addEventListener('click', toggleViews);
            cancelBtn.addEventListener('click', toggleViews);
        });
    </script>

</body>
</html>
<?php
ob_end_flush(); // Send the output buffer and turn off buffering
?>