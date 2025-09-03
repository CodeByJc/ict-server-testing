<?php
include('../../api/db/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_no = mysqli_real_escape_string($conn, $_POST['phone_no']);
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date']);
    $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty_id']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $joining_date = mysqli_real_escape_string($conn, $_POST['joining_date']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');

    // Generate password: firstname@facultyId
    $password = $first_name . '@' . $faculty_id;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // 1. Insert into user_login
        $user_login_query = "INSERT INTO user_login (username, password, role, email, phone_no, device_token) 
                            VALUES ('$faculty_id', '$hashed_password', 'faculty', '$email', '$phone_no', '')";
        mysqli_query($conn, $user_login_query);
        if (mysqli_affected_rows($conn) <= 0) {
            throw new Exception("Failed to insert into user_login");
        }

        // 2. Insert into address_info (only if address is provided)
        $address_id = null;
        if (!empty($address)) {
            $address_query = "INSERT INTO address_info (address) VALUES ('$address')";
            mysqli_query($conn, $address_query);
            if (mysqli_affected_rows($conn) <= 0) {
                throw new Exception("Failed to insert into address_info");
            }
            $address_id = mysqli_insert_id($conn);
        }

        // 3. Insert into faculty_info
        $faculty_query = "INSERT INTO faculty_info (first_name, last_name, faculty_id, address_info_id, user_login_id, 
                         gender, birth_date, designation, joining_date) 
                         VALUES ('$first_name', '$last_name', '$faculty_id', " . ($address_id ? "'$address_id'" : "NULL") . ", '$faculty_id', 
                                 '$gender', '$birth_date', '$designation', '$joining_date')";
        mysqli_query($conn, $faculty_query);
        if (mysqli_affected_rows($conn) <= 0) {
            throw new Exception("Failed to insert into faculty_info");
        }

        // Commit transaction
        mysqli_commit($conn);
        
        // Redirect with success parameter
        header("Location: faculty_list.php?success=add");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        // Redirect with error parameter
        header("Location: add_faculty.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Check for error parameter from redirect
$error = isset($_GET['error']) ? urldecode($_GET['error']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Faculty</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        button[disabled] {
            background-color: #d1d5db; /* Tailwind's gray-300 */
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php 
        $page_title = "Add New Faculty";
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
                                   title="Faculty ID must be 4-6 digits">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">First Name</label>
                            <input type="text" name="first_name" required class="w-full p-2 border-2 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Last Name</label>
                            <input type="text" name="last_name" required class="w-full p-2 border-2 rounded-md">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">College Email ID</label>
                            <input type="email" name="email" required maxlength="100" 
                                   class="w-full p-2 border-2 rounded-md" 
                                   title="Enter a valid email address (max 100 characters)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Phone No</label>
                            <input type="tel" name="phone_no" required pattern="[0-9]{10,15}" 
                                   maxlength="15" class="w-full p-2 border-2 rounded-md" 
                                   title="Phone number must be 10-15 digits">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Joining Date</label>
                            <input type="date" name="joining_date" required class="w-full p-2 border-2 rounded-md">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Gender</label>
                            <select name="gender" required class="w-full p-2 border-2 rounded-md">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Birth Date</label>
                            <input type="date" name="birth_date" required class="w-full p-2 border-2 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Designation</label>
                            <select name="designation" required class="w-full p-2 border-2 rounded-md">
                                <option value="">Select Designation</option>
                                <option value="hod">Head of Department</option>
                                <option value="ap">Assistant Professor</option>
                                <option value="tp">Trainee Professor</option>
                                <option value="la">Lab Assistant</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Address (Optional)</label>
                        <textarea name="address" class="w-full p-2 border-2 rounded-md" rows="4"></textarea>
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
                text: "Do you want to add this faculty member?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#06b6d4',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, add it!'
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

            // Show error message if redirected with error
            <?php if ($error): ?>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to add faculty: <?php echo $error; ?>',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>