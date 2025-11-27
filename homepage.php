<?php
// homepage.php
ob_start(); // Start output buffering

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect him to the login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // CRITICAL FIX: Ensure the user is not redirected to a hardcoded path on logout
    header("Location: index.php");
    exit();
}

// Get current page, default to 'home'
$page = $_GET['page'] ?? 'home';
$user_name = $_SESSION['name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'student';

// Define roles for specific permissions:
// 1. Can manage reports (Technician & Teacher)
$can_manage_reports = ($user_type === 'teacher' || $user_type === 'technician'); 
// 2. Can manage labs structure (Only Teacher)
$can_manage_labs = ($user_type === 'teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixit Lab - Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <!-- Sidebar -->
    <nav id="sidebar" class="bg-gray-800 text-white w-64 h-full fixed top-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-30 flex flex-col">
        <div class="p-6 text-2xl font-bold text-center border-b border-gray-700">
            Fixit Lab
        </div>
        <div class="flex-grow p-4 space-y-2 overflow-y-auto">
            <!-- Common Links -->
            
            <a href="?page=home" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'home' ? 'bg-gray-700' : ''; ?>">Home</a>
            <a href="?page=profile" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'profile' ? 'bg-gray-700' : ''; ?>">Profile</a>
            <a href="?page=labs_view" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'labs_view' ? 'bg-gray-700' : ''; ?>">Lab Overview</a>
            <a href="?page=report" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'report' ? 'bg-gray-700' : ''; ?>">Report Fault</a>
            
            <!-- Repair/Admin Links -->
            <?php if ($can_manage_reports || $can_manage_labs): ?>
                <div class="pt-4 mt-4 border-t border-gray-700">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Repair & Management</p>
                    
                    <?php if ($can_manage_reports): ?>
                        <!-- Admin Dashboard is visible to both Teacher AND Technician -->
                        <a href="?page=admin_dashboard" class="block mt-2 py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'admin_dashboard' ? 'bg-gray-700' : ''; ?>">Admin Dashboard</a>
                        
                        <!-- NEW LINK: Report History (Visible to both staff roles) -->
                        <a href="?page=reports_history" title="View historical reports by date range" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'reports_history' ? 'bg-gray-700' : ''; ?>">Report History</a>
                    <?php endif; ?>
                    
                    <?php if ($can_manage_labs): ?>
                        <!-- Manage Labs is visible ONLY to Teacher -->
                        <a href="?page=manage_labs" class="block py-2.5 px-4 rounded hover:bg-gray-700 <?php echo $page == 'manage_labs' ? 'bg-gray-700' : ''; ?>">Manage Labs</a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        </div>
        <!-- Logout Link -->
        <div class="p-4 border-t border-gray-700">
            <a href="logout.php" class="block py-2 px-4 rounded bg-red-600 hover:bg-red-700 text-center transition">Logout</a>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="md:ml-64 flex flex-col min-h-screen">
        
        <!-- Top Header -->
        <header class="bg-white shadow h-16 flex items-center justify-between px-6 sticky top-0 z-20">
            <button id="menu-toggle" class="md:hidden text-gray-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
            
            <!-- Top Nav Links -->
            <div class="flex items-center space-x-4">
                <a href="?page=home" class="text-sm font-medium text-gray-600 hover:text-gray-900">Home</a>
                <a href="?page=about" class="text-sm font-medium text-gray-600 hover:text-gray-900">About</a>
                <a href="?page=contact" class="text-sm font-medium text-gray-600 hover:text-gray-900">Contact</a>
                <a href="?page=help" class="text-sm font-medium text-gray-600 hover:text-gray-900">Help</a>
            </div>
            
            <!-- User Info -->
            <div class="flex items-center">
                <span class="text-gray-800 font-medium">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                <span class="ml-2 px-2 py-1 text-xs rounded bg-blue-100 text-blue-800 capitalize"><?php echo htmlspecialchars($user_type); ?></span>
            </div>
        </header>

        <!-- Dynamic Page Content -->
        <main class="flex-grow bg-gray-100 p-6">
            <?php
            // Allowed pages list to prevent security issues (LFI)
            $allowed_pages = [
                'home', 'profile', 'labs_view', 'report', 
                'admin_dashboard', 'manage_labs', 'edit_lab',
                'about', 'contact', 'help', 'pc_history', 'reports_history' 
            ];

            if (in_array($page, $allowed_pages)) {
                $file = $page . '.php'; 
                if (file_exists($file)) {
                    // CRITICAL: Set this flag for fragments like contact.php to render correctly.
                    $embedded_in_homepage = true;
                    include $file;
                } else {
                    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
                            <strong class='font-bold'>Error!</strong>
                            <span class='block sm:inline'>File '{$file}' not found. Please ensure it exists.</span>
                          </div>";
                }
            } else {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
                        <strong class='font-bold'>Error!</strong>
                        <span class='block sm:inline'>Page not found.</span>
                      </div>";
            }
            ?>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t p-4 text-center text-gray-500 text-sm">
            &copy; <?php echo date("Y"); ?> Fixit Lab. All rights reserved.
        </footer>
    </div>

    <script>
        // Sidebar Toggle for Mobile
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>