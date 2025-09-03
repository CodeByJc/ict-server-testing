<?php
include('../../api/db/db_connection.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'];
$user = $_SESSION['user'];

if (!isset($_SESSION['image_url'])) {
    $imageUrl = "https://marwadieducation.edu.in/MEFOnline/handler/getImage.ashx?Id=" . htmlspecialchars($user['username']);
    $_SESSION['image_url'] = $imageUrl;
} else {
    $imageUrl = $_SESSION['image_url'];
}
?>

<nav class="pl-10 flex justify-between items-center text-gray">
    <div class="text-xl pt-2 font-bold">
        <?php echo isset($page_title) ? htmlspecialchars($page_title) : ""; ?>
    </div>
    
    <div class="relative">
        <div id="user-info" class="p-4 rounded-bl-xl border bg-white flex flex-row cursor-pointer">
            <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center">
                <img
                    class="w-full h-full object-cover"
                    src="<?php echo $imageUrl; ?>"
                    alt="Faculty Image"
                    onerror="this.onerror=null; this.src='../assets/images/favicon.png'; this.classList.add('p-3');">
            </div>
            <div class="pl-3">
                <span class="block text-md font-medium">
                    <?php echo ($userdata['first_name'] . " " . $userdata['last_name']); ?>
                </span>
                <span class="block text-sm text-gray-400">
                    <?php
                    echo match ($userdata['designation']) {
                        'hod' => 'HOD-ICT',
                        'ap' => 'Assistant Professor',
                        'tp' => 'Trainee Professor',
                        default => 'Null',
                    };
                    ?>
                </span>
            </div>
        </div>

        <!-- Dropdown Menu -->
        <div id="dropdown-menu" class="absolute right-0 w-full shadow bg-white border rounded-b-xl opacity-0 transform translate-y-2 transition-all duration-300 hidden">
            <ul class="py-2">
                <!-- <li class="group/profile flex items-center cursor-pointer hover:bg-gray-50">
                    <div class="h-8 w-0 bg-cyan-500 group-hover/profile:w-1 transition-all duration-100"></div>
                    <a href="profile.php" class="block px-4 py-2 text-gray-700 group-hover/profile:text-cyan-500 transition-all">
                        Profile
                    </a>
                </li> -->
                <li class="group/profile flex items-center cursor-pointer hover:bg-gray-50">
                    <div class="h-8 w-0 bg-red-500 group-hover/profile:w-1 transition-all duration-100"></div>
                    <a href="../logout.php" class="block px-4 py-2 text-gray-700 group-hover/profile:text-red-500 group-hover/profile:font-bold transition-all">
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
    const userInfo = document.getElementById('user-info');
    const dropdownMenu = document.getElementById('dropdown-menu');

    userInfo.addEventListener('click', () => {
        if (dropdownMenu.classList.contains('hidden')) {
            // Show dropdown
            dropdownMenu.classList.remove('hidden');
            setTimeout(() => {
                dropdownMenu.classList.remove('opacity-0', 'translate-y-2');
                dropdownMenu.classList.add('opacity-100', 'translate-y-0');
                userInfo.classList.remove('rounded-bl-xl');
            }, 10); // Small delay to ensure transition works
        } else {
            // Hide dropdown
            dropdownMenu.classList.remove('opacity-100', 'translate-y-0');
            dropdownMenu.classList.add('opacity-0', 'translate-y-2');
            userInfo.classList.add('rounded-bl-xl');
            setTimeout(() => {
                dropdownMenu.classList.add('hidden');
            }, 300); // Matches transition duration
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (event) => {
        if (!userInfo.contains(event.target) && !dropdownMenu.contains(event.target)) {
            dropdownMenu.classList.remove('opacity-100', 'translate-y-0');
            dropdownMenu.classList.add('opacity-0', 'translate-y-2');
            userInfo.classList.add('rounded-bl-xl');
            setTimeout(() => {
                dropdownMenu.classList.add('hidden');
            }, 300); // Matches transition duration
        }
    });
</script>