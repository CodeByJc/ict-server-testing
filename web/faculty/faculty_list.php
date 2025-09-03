<?php
session_start(); // Start the session to access $_SESSION if needed
include('../../api/db/db_connection.php');

$current_faculty_id = isset($_SESSION['faculty_id']);

$faculty_query = "SELECT *,
    CASE
        WHEN designation = 'hod' THEN 'Head Of Department'
        WHEN designation = 'ap' THEN 'Assistant Professor'
        WHEN designation = 'tp' THEN 'Trainee Professor'
        WHEN designation = 'la' THEN 'Lab Assistant'
        ELSE designation
    END AS designation_full
FROM faculty_info;";
$faculty_result = mysqli_query($conn, $faculty_query);

// Check for success parameter from delete redirect
$success = isset($_GET['success']) && $_GET['success'] === 'delete';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculties</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Faculties";
        include('./navbar.php');
        ?>
        <div class="p-6">
            <a href="add_faculty.php" 
               class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-cyan-600 rounded-md mb-6 transition-all inline-block">
                Add New Faculty
            </a>
        </div>
        <div id="leaves-grid" class="grid grid-cols-2 gap-3 px-5 mb-5">
            <?php while ($faculty = mysqli_fetch_assoc($faculty_result)): ?>
                <?php 
                    $imageUrl = "https://marwadieducation.edu.in/MEFOnline/handler/getImage.ashx?Id=" . $faculty['faculty_id']; 
                    $fallbackImage = "../assets/images/favicon.png";
                ?>
                <div class="faculty-item py-2 bg-white border-2 rounded-lg p-2 hover:pl-5 hover:border-2 hover:border-gray-400 transition-all cursor-pointer flex items-center space-x-4" onclick="window.location.href='faculty_details.php?faculty_id=<?php echo $faculty['id']; ?>'">
                    <!-- Faculty Image with Fallback -->
                    <div class='border-2 rounded-lg'>
                        <img
                            class="w-20 h-22 object-cover transition-all"
                            src="<?php echo $imageUrl; ?>"
                            alt="Faculty Image"
                            onerror="this.onerror=null; this.src='<?php echo $fallbackImage; ?>'; this.classList.add('p-5', 'grayscale', 'opacity-25');">
                    </div>

                    <!-- Faculty Details -->
                    <div class="space-y-1">
                        <div><strong>Name:</strong> <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></div>
                        <div><strong>ID:</strong> <?php echo $faculty['faculty_id']; ?> <strong class="ml-4">Gender:</strong> <?php echo strtoupper($faculty['gender']); ?></div>
                        <div><strong>Designation:</strong> <?php echo $faculty['designation_full']; ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        // Show success message if redirected from delete
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($success): ?>
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Faculty member has been deleted successfully',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>