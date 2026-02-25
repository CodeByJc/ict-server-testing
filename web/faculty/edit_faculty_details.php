<?php
include('../../api/db/db_connection.php');

$faculty_id = intval($_GET['faculty_id']);
$faculty_query = "SELECT fi.*, adi.address AS full_address, ul.phone_no, ul.email
    FROM faculty_info fi
    LEFT JOIN address_info adi ON fi.address_info_id = adi.id 
    JOIN user_login ul ON fi.user_login_id = ul.username
    WHERE fi.id = $faculty_id";
$faculty_result = mysqli_query($conn, $faculty_query);
$faculty = mysqli_fetch_assoc($faculty_result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_no = mysqli_real_escape_string($conn, $_POST['phone_no']);
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date']);
    $faculty_id_input = mysqli_real_escape_string($conn, $_POST['faculty_id']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $joining_date = mysqli_real_escape_string($conn, $_POST['joining_date']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');

    mysqli_begin_transaction($conn);
    try {
        // Update user_login
        $update_user_query = "UPDATE user_login SET email = '$email', phone_no = '$phone_no' 
                             WHERE username = '{$faculty['faculty_id']}'";
        mysqli_query($conn, $update_user_query);
        if (mysqli_affected_rows($conn) === -1) {
            throw new Exception("Failed to update user_login");
        }

        // Update or Insert address_info
        if (!empty($address)) {
            if ($faculty['address_info_id']) {
                $update_address_query = "UPDATE address_info SET address = '$address' 
                                        WHERE id = {$faculty['address_info_id']}";
                mysqli_query($conn, $update_address_query);
                if (mysqli_affected_rows($conn) === -1) {
                    throw new Exception("Failed to update address_info");
                }
            } else {
                $insert_address_query = "INSERT INTO address_info (address) VALUES ('$address')";
                mysqli_query($conn, $insert_address_query);
                if (mysqli_affected_rows($conn) <= 0) {
                    throw new Exception("Failed to insert address_info");
                }
                $faculty['address_info_id'] = mysqli_insert_id($conn);
            }
        } elseif ($faculty['address_info_id']) {
            $delete_address_query = "DELETE FROM address_info WHERE id = {$faculty['address_info_id']}";
            mysqli_query($conn, $delete_address_query);
            if (mysqli_affected_rows($conn) === -1) {
                throw new Exception("Failed to delete address_info");
            }
            $faculty['address_info_id'] = null;
        }

        // Update faculty_info
        $update_faculty_query = "UPDATE faculty_info SET 
            first_name = '$first_name', 
            last_name = '$last_name', 
            gender = '$gender', 
            birth_date = '$birth_date', 
            designation = '$designation', 
            joining_date = '$joining_date', 
            address_info_id = " . ($faculty['address_info_id'] ? "'{$faculty['address_info_id']}'" : "NULL") . " 
            WHERE id = $faculty_id";
        mysqli_query($conn, $update_faculty_query);
        if (mysqli_affected_rows($conn) === -1) {
            throw new Exception("Failed to update faculty_info");
        }

        mysqli_commit($conn);

        // Redirect with success message
        header("Location: faculty_details.php?faculty_id=$faculty_id&success=1");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        // Redirect with error message
        header("Location: edit_faculty_details.php?faculty_id=$faculty_id&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Check for success or error messages from redirect
$success = isset($_GET['success']) && $_GET['success'] == 1;
$error = isset($_GET['error']) ? urldecode($_GET['error']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Faculty Details</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        button[disabled] {
            background-color: #d1d5db;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php 
        $page_title = "Edit Faculty Details";
        include('./navbar.php'); 
        ?>
        
        <div class="p-6">
            <div class="bg-white rounded-lg shadow-md p-6 max-w-full">
                <form id="facultyForm" method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Faculty ID</label>
                            <input type="text" name="faculty_id" required pattern="[0-9]{4,6}" 
                                   maxlength="6" class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['faculty_id']; ?>" readonly 
                                   title="Faculty ID must be 4-6 digits">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">First Name</label>
                            <input type="text" name="first_name" required class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['first_name']; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Last Name</label>
                            <input type="text" name="last_name" required class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['last_name']; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">College Email ID</label>
                            <input type="email" name="email" required maxlength="100" 
                                   class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['email']; ?>" 
                                   title="Enter a valid email address (max 100 characters)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Phone No</label>
                            <input type="tel" name="phone_no" required pattern="[0-9]{10,15}" 
                                   maxlength="15" class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['phone_no']; ?>" 
                                   title="Phone number must be 10-15 digits">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Joining Date</label>
                            <input type="date" name="joining_date" required class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['joining_date']; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Gender</label>
                            <select name="gender" required class="w-full p-2 border-2 rounded-md">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $faculty['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $faculty['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Birth Date</label>
                            <input type="date" name="birth_date" required class="w-full p-2 border-2 rounded-md" 
                                   value="<?php echo $faculty['birth_date']; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Designation</label>
                            <select name="designation" required class="w-full p-2 border-2 rounded-md">
                                <option value="">Select Designation</option>
                                <option value="hod" <?php echo $faculty['designation'] === 'hod' ? 'selected' : ''; ?>>Head of Department</option>
                                <option value="ap" <?php echo $faculty['designation'] === 'ap' ? 'selected' : ''; ?>>Assistant Professor</option>
                                <option value="tp" <?php echo $faculty['designation'] === 'tp' ? 'selected' : ''; ?>>Trainee Professor</option>
                                <option value="la" <?php echo $faculty['designation'] === 'la' ? 'selected' : ''; ?>>Lab Assistant</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Address (Optional)</label>
                        <textarea name="address" class="w-full p-2 border-2 rounded-md" rows="4"><?php echo $faculty['full_address'] ?? ''; ?></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" id="submitBtn" onclick="confirmSubmit()" 
                                class="bg-cyan-500 hover:bg-cyan-600 text-white px-6 py-2 rounded-md transition-all" disabled>
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmSubmit() {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to update this faculty member's details?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#06b6d4',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('facultyForm').submit();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('facultyForm');
            const submitBtn = document.getElementById('submitBtn');
            const requiredFields = form.querySelectorAll('[required]');

            function checkFormValidity() {
                let allFilled = true;
                requiredFields.forEach(field => {
                    if (!field.value.trim() || !field.checkValidity()) {
                        allFilled = false;
                    }
                });
                submitBtn.disabled = !allFilled;
            }

            requiredFields.forEach(field => {
                field.addEventListener('input', checkFormValidity);
            });

            checkFormValidity();

            // Show SweetAlert based on URL parameters
            <?php if ($success): ?>
                Swal.fire({
                    title: 'Success!',
                    text: 'Faculty details updated successfully',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'faculty_details.php?faculty_id=<?php echo $faculty_id; ?>';
                });
            <?php elseif ($error): ?>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to update faculty: <?php echo $error; ?>',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>