<?php
session_start(); // Start the session
include('../../api/db/db_connection.php');

$faculty_id = intval($_GET['faculty_id']);
$userdata = $_SESSION['userdata'];
$session_faculty_name = $userdata['first_name'] . " " . $userdata['last_name'];

// Query to get faculty details with address information
$faculty_query = "SELECT fi.*, 
    CASE
        WHEN fi.designation = 'hod' THEN 'Head Of Department'
        WHEN fi.designation = 'ap' THEN 'Assistant Professor'
        WHEN fi.designation = 'tp' THEN 'Trainee Professor'
        WHEN fi.designation = 'la' THEN 'Lab Assistant'
        ELSE fi.designation
    END AS designation_full,
    adi.address AS full_address,
    ul.phone_no,
    ul.email
    FROM faculty_info fi
    LEFT JOIN address_info adi ON fi.address_info_id = adi.id 
    JOIN user_login ul ON fi.user_login_id = ul.username
    WHERE fi.id = $faculty_id";

$faculty_result = mysqli_query($conn, $faculty_query);
$faculty = mysqli_fetch_assoc($faculty_result);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    $password = $_POST['password'];

    $userdata = $_SESSION['userdata'];
    // Get the faculty_id from session (assuming it's stored as 'faculty_id' in session)
    $session_faculty_id = isset($userdata['faculty_id']) ? $userdata['faculty_id'] : null;
    
    if (!$session_faculty_id) {
        // Redirect if no session faculty_id is found
        header("Location: faculty_details.php?faculty_id=$faculty_id&error=No session found, please log in");
        exit();
    }

    // Query to get the password from user_login for the session faculty_id
    $admin_query = "SELECT password FROM user_login WHERE username = '$session_faculty_id'";
    $admin_result = mysqli_query($conn, $admin_query);
    $admin = mysqli_fetch_assoc($admin_result);

    if ($admin && password_verify($password, $admin['password'])) {
        // Password correct, proceed with deletion
        mysqli_begin_transaction($conn);
        try {
            $delete_faculty_query = "DELETE FROM faculty_info WHERE id = $faculty_id";
            mysqli_query($conn, $delete_faculty_query);
            if (mysqli_affected_rows($conn) <= 0) {
                throw new Exception("Failed to delete faculty_info");
            }

            $delete_user_query = "DELETE FROM user_login WHERE username = '{$faculty['faculty_id']}'";
            mysqli_query($conn, $delete_user_query);
            if (mysqli_affected_rows($conn) <= 0) {
                throw new Exception("Failed to delete user_login");
            }

            if ($faculty['address_info_id']) {
                $delete_address_query = "DELETE FROM address_info WHERE id = {$faculty['address_info_id']}";
                mysqli_query($conn, $delete_address_query);
            }

            mysqli_commit($conn);
            // Redirect with success parameter
            header("Location: faculty_list.php?success=delete");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            // Redirect with error parameter
            header("Location: faculty_details.php?faculty_id=$faculty_id&error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        // Redirect with error for incorrect password or no user found
        header("Location: faculty_details.php?faculty_id=$faculty_id&error=Incorrect password or user not found");
        exit();
    }
}

// Check for success or error parameters from redirect
$success = isset($_GET['success']) && $_GET['success'] === 'delete';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Details - <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }
        .label {
            width: 100px;
            font-weight: bold;
        }
        .value {
            flex: 1;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php 
        $page_title = "Faculty Details";
        include('./navbar.php'); 
        ?>
        
        <div class="p-6">
            <!-- Back, Edit, Delete Buttons -->
            <div class="mb-6 flex gap-4">
                <a href="faculty_list.php" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition-all">
                    Back to Faculty List
                </a>
                <a href="edit_faculty_details.php?faculty_id=<?php echo $faculty_id; ?>" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition-all">
                    Edit
                </a>
                <a href="faculty_punch_details.php?faculty_id=<?php echo $faculty_id; ?>&faculty_name=<?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>" 
                   class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition-all">
                    Punch Details
                </a>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 max-w-5xl">
                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Faculty Image -->
                    <div class="flex-shrink-0">
                        <?php 
                        $imageUrl = "https://marwadieducation.edu.in/MEFOnline/handler/getImage.ashx?Id=" . $faculty['faculty_id']; 
                        $fallbackImage = "../assets/images/favicon.png";
                        ?>
                        <img 
                            class="w-48 h-64 object-cover rounded-lg border-2 border-gray-200"
                            src="<?php echo $imageUrl; ?>"
                            alt="Faculty Image"
                            onerror="this.onerror=null; this.src='<?php echo $fallbackImage; ?>'; this.classList.add('w-md','p-5', 'grayscale', 'opacity-25');">
                    </div>

                    <!-- Faculty Details -->
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold mb-4"><?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></h2>
                        
                        <div class="mb-4">
                            <div class="detail-row">
                                <span class="label">Faculty ID:</span>
                                <span class="value text-cyan-500"><?php echo $faculty['faculty_id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Designation:</span>
                                <span class="value text-cyan-500"><?php echo $faculty['designation_full']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Gender:</span>
                                <span class="value text-cyan-500"><?php echo strtoupper($faculty['gender']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Email:</span>
                                <span class="value text-cyan-500"><?php echo $faculty['email'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Phone:</span>
                                <span class="value text-cyan-500"><?php echo $faculty['phone_no'] ?? 'N/A'; ?></span>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="detail-row">
                            <span class="label">Address:</span>
                            <span class="value text-cyan-500"><?php echo $faculty['full_address'] ?? 'N/A'; ?></span>
                        </div>
                    </div>
                    
                </div>
            </div>
            <button onclick="promptDelete('<?php echo htmlspecialchars($session_faculty_name); ?>')" 
                        class="bg-red-500 hover:bg-red-600 text-white mt-5 px-4 py-2 rounded-md transition-all">
                    Delete Faculty  
                </button>
        </div>
    </div>

    <script>
        function promptDelete(faculty_name) {
            Swal.fire({
                title: 'Delete Faculty Confirmation',
                input: 'password',
                inputLabel: faculty_name + ', enter your password',
                inputPlaceholder: 'Enter your password',
                showCancelButton: true,
                confirmButtonText: 'Submit',
                cancelButtonText: 'Cancel',
                preConfirm: (password) => {
                    if (!password) {
                        Swal.showValidationMessage('Password is required');
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "Do you really want to delete this faculty member?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((confirmResult) => {
                        if (confirmResult.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'password';
                            input.value = result.value;
                            form.appendChild(input);
                            const deleteInput = document.createElement('input');
                            deleteInput.type = 'hidden';
                            deleteInput.name = 'delete_faculty';
                            deleteInput.value = '1';
                            form.appendChild(deleteInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }
            });
        }

        // Show success or error message based on URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($success): ?>
                Swal.fire({
                    title: 'Deleted!',
                    text: 'Faculty member has been deleted successfully',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            <?php elseif ($error): ?>
                Swal.fire({
                    title: 'Error!',
                    text: '<?php echo $error; ?>',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>