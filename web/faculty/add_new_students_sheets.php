<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../api/db/db_connection.php';
require '../vendor/autoload.php'; // PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check database connection
if (mysqli_connect_errno()) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed");
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

// Handle file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (in_array($fileExtension, ['xlsx', 'xls'])) {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $students = [];

            foreach ($sheetData as $rowIndex => $row) {
                if ($rowIndex == 1 || empty($row['A'])) {
                    continue; // Skip header or empty rows
                }

                $students[] = [
                    'enrollment_no' => $row['A'],
                    'gr_no' => $row['B'],
                    'first_name' => $row['C'],
                    'last_name' => $row['D'],
                    'gender' => $row['E'],
                    'student_email' => $row['F'],
                    'student_phone' => $row['G'],
                    'sem' => $row['H'],
                    'edu_type' => strtolower($row['I']),
                    'batch_start_year' => $row['J'],
                    'batch_end_year' => $row['K'],
                    'parent_name' => $row['L'],
                    'parent_phone' => $row['M']
                ];
            }

            // Store in session for preview
            $_SESSION['uploaded_students'] = $students;
            $_SESSION['upload_message'] = ['type' => 'success', 'text' => 'File uploaded successfully. Please review the data below.'];
        } catch (Exception $e) {
            error_log("Excel parsing error: " . $e->getMessage());
            $_SESSION['upload_message'] = ['type' => 'error', 'text' => 'Failed to parse Excel file.'];
        }
    } else {
        $_SESSION['upload_message'] = ['type' => 'error', 'text' => 'Only Excel files (.xlsx, .xls) are allowed.'];
    }
}

// Handle AJAX insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_students') {
    header('Content-Type: application/json');
    ob_end_clean();

    if (!isset($_SESSION['uploaded_students']) || empty($_SESSION['uploaded_students'])) {
        echo json_encode(['status' => 'error', 'message' => 'No data to insert']);
        exit;
    }

    $students = $_SESSION['uploaded_students'];
    $success_count = 0;
    $errors = [];

    foreach ($students as $student) {
        try {
            // Validate data
            if (empty($student['enrollment_no']) || empty($student['gr_no']) || empty($student['first_name']) || empty($student['last_name'])) {
                $errors[] = "Missing required fields for enrollment_no: {$student['enrollment_no']}";
                continue;
            }

            // 1. Insert Parent into user_login
            $parent_username = $student['gr_no'];
            $parent_password = password_hash("{$student['first_name']}@{$student['gr_no']}", PASSWORD_DEFAULT);
            $parent_role = 'parent';
            $parent_phone = $student['parent_phone'] ?? null;

            $stmt = $conn->prepare("SELECT username FROM user_login WHERE username = ?");
            $stmt->bind_param("s", $parent_username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO user_login (username, password, role, email, isactive, phone_no) VALUES (?, ?, ?, NULL, 1, ?)");
                $stmt->bind_param("ssss", $parent_username, $parent_password, $parent_role, $parent_phone);
                $stmt->execute();
            }

            // 2. Insert into parents_info
            $stmt = $conn->prepare("INSERT INTO parents_info (user_login_id, parent_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $parent_username, $student['parent_name']);
            $stmt->execute();
            $parent_info_id = $conn->insert_id;

            // 3. Insert Student into user_login
            $student_username = $student['enrollment_no'];
            $student_password = password_hash("{$student['first_name']}@{$student['gr_no']}", PASSWORD_DEFAULT);
            $student_role = 'student';
            $student_email = $student['student_email'] ?? null;
            $student_phone = $student['student_phone'] ?? null;

            $stmt = $conn->prepare("SELECT username FROM user_login WHERE username = ?");
            $stmt->bind_param("s", $student_username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO user_login (username, password, role, isactive, email, phone_no) VALUES (?, ?, ?, 1, ?, ?)");
                $stmt->bind_param("sssss", $student_username, $student_password, $student_role, $student_email, $student_phone);
                $stmt->execute();
            }

            // 4. Get or insert sem_info
            $stmt = $conn->prepare("SELECT id FROM sem_info WHERE sem = ? AND edu_type = ?");
            $stmt->bind_param("is", $student['sem'], $student['edu_type']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $sem_info_id = $result->fetch_assoc()['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO sem_info (sem, edu_type) VALUES (?, ?)");
                $stmt->bind_param("is", $student['sem'], $student['edu_type']);
                $stmt->execute();
                $sem_info_id = $conn->insert_id;
            }

            // 5. Get or insert batch_info
            $stmt = $conn->prepare("SELECT id FROM batch_info WHERE batch_start_year = ? AND batch_end_year = ?");
            $stmt->bind_param("ii", $student['batch_start_year'], $student['batch_end_year']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $batch_info_id = $result->fetch_assoc()['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO batch_info (batch_start_year, batch_end_year) VALUES (?, ?)");
                $stmt->bind_param("ii", $student['batch_start_year'], $student['batch_end_year']);
                $stmt->execute();
                $batch_info_id = $conn->insert_id;
            }

            // 6. Insert into student_info
            $stmt = $conn->prepare("INSERT INTO student_info (user_login_id, enrollment_no, gr_no, first_name, last_name, gender, parent_info_id, sem_info_id, batch_info_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssii", $student_username, $student['enrollment_no'], $student['gr_no'], 
                             $student['first_name'], $student['last_name'], $student['gender'], 
                             $parent_info_id, $sem_info_id, $batch_info_id);
            $stmt->execute();

            $success_count++;
        } catch (Exception $e) {
            error_log("Insert error for enrollment_no {$student['enrollment_no']}: " . $e->getMessage());
            $errors[] = "Failed to insert enrollment_no: {$student['enrollment_no']}";
        }
    }

    // Clear session data
    unset($_SESSION['uploaded_students']);
    if ($success_count === count($students)) {
        echo json_encode(['status' => 'success', 'message' => "Successfully inserted $success_count students."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Inserted $success_count students. Errors: " . implode(', ', $errors)]);
    }
    exit;
}

// Handle AJAX clear preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_preview') {
    header('Content-Type: application/json');
    ob_end_clean();
    unset($_SESSION['uploaded_students']);
    echo json_encode(['status' => 'success', 'message' => 'Preview data cleared.']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Students from Excel</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #students-table {
            border-collapse: collapse;
            width: 100%;
        }
        #students-table th,
        #students-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            min-width: 150px;
            padding: 8px;
        }
        #students-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #students-table tbody tr:hover {
            background-color: #e5e7eb;
            font-weight: bold;
            cursor: pointer;
        }
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Add New Students from Excel";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <!-- Upload Form -->
            <div class="bg-white p-6 rounded-lg drop-shadow-xl mb-6">
                <h1 class="text-3xl font-bold">Upload Student Data</h1>
                <div class="rounded-full w-full h-1 mt-2 bg-slate-100"></div>
                <form id="upload-form" method="post" enctype="multipart/form-data" class="mt-4">
                    <div class="flex items-center space-x-4">
                        <input type="file" name="file" id="file" accept=".xlsx, .xls" class="border rounded p-2" required>
                        <button type="submit" class="bg-cyan-600 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                            <i class="fa-solid fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
                <?php if (isset($_SESSION['upload_message'])): ?>
                    <div class="mt-4 text-<?php echo $_SESSION['upload_message']['type'] === 'success' ? 'green' : 'red'; ?>-600">
                        <?php echo htmlspecialchars($_SESSION['upload_message']['text']); ?>
                    </div>
                    <?php unset($_SESSION['upload_message']); ?>
                <?php endif; ?>
            </div>
            <!-- Preview Table -->
            <?php if (isset($_SESSION['uploaded_students']) && !empty($_SESSION['uploaded_students'])): ?>
                <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold">Preview Student Data</h2>
                        <div class="space-x-2">
                            <button id="add-all-btn" class="bg-green-500 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                                <i class="fa-solid fa-plus"></i> Add All Data
                            </button>
                            <button id="clear-all-btn" class="bg-red-500 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                                <i class="fa-solid fa-trash"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="students-table" class="min-w-full bg-white shadow-md rounded-md">
                            <thead>
                                <tr class="bg-gray-700 text-white">
                                    <th class="border px-4 py-2 rounded-tl-md">Enrollment No</th>
                                    <th class="border px-4 py-2">GR No</th>
                                    <th class="border px-4 py-2">First Name</th>
                                    <th class="border px-4 py-2">Last Name</th>
                                    <th class="border px-4 py-2">Gender</th>
                                    <th class="border px-4 py-2">Email</th>
                                    <th class="border px-4 py-2">Phone</th>
                                    <th class="border px-4 py-2">Semester</th>
                                    <th class="border px-4 py-2">Edu Type</th>
                                    <th class="border px-4 py-2">Batch Start</th>
                                    <th class="border px-4 py-2">Batch End</th>
                                    <th class="border px-4 py-2">Parent Name</th>
                                    <th class="border px-4 py-2 rounded-tr-md">Parent Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['uploaded_students'] as $student): ?>
                                    <tr>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['enrollment_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['gr_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['first_name']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['last_name']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['gender']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['student_email'] ?? ''); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['student_phone'] ?? ''); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['sem']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['edu_type']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['batch_start_year']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['batch_end_year']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['parent_name']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with horizontal scrolling
            $('#students-table').DataTable({
                scrollX: true,
                paging: false,
                searching: false,
                ordering: true,
                info: false,
                columnDefs: [
                    { width: '150px', targets: '_all' }
                ]
            });

            // Handle Add All Data button
            $('#add-all-btn').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to add all data to the database?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, add it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'add_new_students_sheets.php',
                            method: 'POST',
                            data: { action: 'insert_students' },
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Success!', data.message, 'success').then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', data.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Insert AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to insert data. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });

            // Handle Clear All button
            $('#clear-all-btn').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This will clear all preview data.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, clear it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'add_new_students_sheets.php',
                            method: 'POST',
                            data: { action: 'clear_preview' },
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Success!', data.message, 'success').then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', data.message || 'Failed to clear preview.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Clear AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to clear preview. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });

            // Show upload message if exists
            <?php if (isset($_SESSION['upload_message'])): ?>
                Swal.fire({
                    icon: '<?php echo $_SESSION['upload_message']['type']; ?>',
                    title: '<?php echo $_SESSION['upload_message']['type'] === 'success' ? 'Success' : 'Error'; ?>',
                    text: '<?php echo $_SESSION['upload_message']['text']; ?>',
                    confirmButtonColor: '#06b6d4'
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php ob_end_flush(); // Flush output buffer ?>