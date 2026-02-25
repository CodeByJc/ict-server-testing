<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_end_clean();

    if ($_POST['action'] === 'fetch_students') {
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        // if ($batch_id <= 0) {
        //     echo json_encode(['status' => 'error', 'message' => 'Invalid batch ID']);
        //     exit;
        // }
        try {
            $query = "SELECT pse.id, CONCAT(si.first_name, ' ', si.last_name) AS student_name, si.enrollment_no, si.gr_no 
                      FROM placement_support_enroll pse 
                      JOIN student_info si ON pse.student_info_id = si.id 
                      WHERE si.batch_info_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $batch_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $students = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $students[] = $row;
            }
            mysqli_stmt_close($stmt);
            echo json_encode(['status' => 'success', 'students' => $students]);
        } catch (Exception $e) {
            error_log("fetch_students error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch students']);
        }
        exit;
    }

    if ($_POST['action'] === 'fetch_available_students') {
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        if ($batch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid batch ID']);
            exit;
        }
        try {
            $query = "SELECT id, CONCAT(first_name, ' ', last_name) AS student_name 
                      FROM student_info 
                      WHERE batch_info_id = ? 
                      AND id NOT IN (SELECT student_info_id FROM placement_support_enroll)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $batch_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $students = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $students[] = $row;
            }
            mysqli_stmt_close($stmt);
            echo json_encode(['status' => 'success', 'students' => $students]);
        } catch (Exception $e) {
            error_log("fetch_available_students error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch available students']);
        }
        exit;
    }

    if ($_POST['action'] === 'add_student') {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        if ($student_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
            exit;
        }
        try {
            $query = "INSERT INTO placement_support_enroll (student_info_id) VALUES (?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $student_id);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($success) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to enroll student']);
            }
        } catch (Exception $e) {
            error_log("add_student error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to enroll student']);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_student') {
        $enroll_id = isset($_POST['enroll_id']) ? intval($_POST['enroll_id']) : 0;
        if ($enroll_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid enrollment ID']);
            exit;
        }
        try {
            $query = "DELETE FROM placement_support_enroll WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $enroll_id);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($success) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete enrollment']);
            }
        } catch (Exception $e) {
            error_log("delete_student error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete enrollment']);
        }
        exit;
    }
}

// Fetch batches for dropdown
$batch_query = "SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year DESC";
$batch_result = mysqli_query($conn, $batch_query);
$batches = [];
while ($row = mysqli_fetch_assoc($batch_result)) {
    $batches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Support Enrollment</title>
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
        }
        #students-table th,
        #students-table td {
            text-align: center;
            border: 1px solid #d1d5db;
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
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Placement Support Enrollment";
        include('./navbar.php');
        ?>
        <div class="mt-6">
            <div class="flex justify-between items-center ml-5 mr-5">
                <select id="batch-select" class="shadow-lg pl-4 p-2 rounded-md w-1/2">
                    <option value="">Select Batch</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?php echo $batch['id']; ?>">
                            <?php echo $batch['batch_start_year'] . ' - ' . $batch['batch_end_year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="add-student-btn" class="bg-cyan-500 shadow-md mr-7 hover:shadow-xl px-6 text-white p-2 rounded-lg hover:bg-cyan-600 transition-all" disabled>
                    <i class="fa-solid fa-plus"></i> Add Student
                </button>
            </div>
        </div>
        <div class="p-5">
            <table id="students-table" class="min-w-full bg-white shadow-md rounded-md table-fixed">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="border px-4 py-2 rounded-tl-md w-3/12">Student Name</th>
                        <th class="border px-4 py-2 w-3/12">Enrollment No</th>
                        <th class="border px-4 py-2 w-3/12">GR No</th>
                        <th class="border px-4 py-2 rounded-tr-md w-3/12">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const table = $('#students-table').DataTable({
                ajax: {
                    url: 'placement_support_enroll.php',
                    type: 'POST',
                    data: function() {
                        return { action: 'fetch_students', batch_id: $('#batch-select').val() };
                    },
                    dataSrc: function(json) {
                        if (json.status === 'success') {
                            return json.students;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: json.message || 'Failed to load students.',
                                confirmButtonColor: '#06b6d4'
                            });
                            return [];
                        }
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX error:', error, thrown, 'Response:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to load students. Check the console for details.',
                            confirmButtonColor: '#06b6d4'
                        });
                    }
                },
                columns: [
                    { data: 'student_name' },
                    { data: 'enrollment_no' },
                    { data: 'gr_no' },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `<button class="delete-btn bg-red-500 text-white p-2 px-4 rounded-full hover:bg-red-600 transition-all" data-id="${row.id}">
                                        <i class="fa-regular fa-trash-can"></i> Delete
                                    </button>`;
                        }
                    }
                ],
                paging: false,
                searching: false,
                ordering: false,
                info: false
            });

            // Enable/disable Add Student button based on batch selection
            $('#batch-select').change(function() {
                const batchId = $(this).val();
                $('#add-student-btn').prop('disabled', !batchId);
                if (batchId) {
                    table.ajax.reload();
                } else {
                    table.clear().draw();
                }
            });

            // Add Student button click
            $('#add-student-btn').click(function() {
                const batchId = $('#batch-select').val();
                if (!batchId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Select Batch',
                        text: 'Please select a batch first.',
                        confirmButtonColor: '#06b6d4'
                    });
                    return;
                }

                // Fetch available students
                $.ajax({
                    url: 'placement_support_enroll.php',
                    method: 'POST',
                    data: { action: 'fetch_available_students', batch_id: batchId },
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            let options = '<option value="">Select Student</option>';
                            data.students.forEach(student => {
                                options += `<option value="${student.id}">${student.student_name}</option>`;
                            });
                            Swal.fire({
                                title: 'Add Student',
                                html: `
                                    <select id="student-select" class="swal2-select w-full p-2 border rounded">
                                        ${options}
                                    </select>
                                `,
                                showCancelButton: true,
                                confirmButtonColor: '#06b6d4',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Add',
                                preConfirm: () => {
                                    const studentId = $('#student-select').val();
                                    if (!studentId) {
                                        Swal.showValidationMessage('Please select a student');
                                        return false;
                                    }
                                    return studentId;
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: 'placement_support_enroll.php',
                                        method: 'POST',
                                        data: { action: 'add_student', student_id: result.value },
                                        dataType: 'json',
                                        success: function(data) {
                                            if (data.status === 'success') {
                                                Swal.fire('Success!', 'Student enrolled successfully.', 'success').then(() => {
                                                    table.ajax.reload();
                                                });
                                            } else {
                                                Swal.fire('Error!', data.message || 'Failed to enroll student.', 'error');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('Add student AJAX error:', status, error, 'Response:', xhr.responseText);
                                            Swal.fire('Error!', 'Failed to enroll student.', 'error');
                                        }
                                    });
                                }
                            });
                        } else {
                            Swal.fire('Error!', data.message || 'No available students found.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Fetch available students AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error!', 'Failed to fetch available students.', 'error');
                    }
                });
            });

            // Delete button click
            $('#students-table tbody').on('click', '.delete-btn', function() {
                const enrollId = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'placement_support_enroll.php',
                            method: 'POST',
                            data: { action: 'delete_student', enroll_id: enrollId },
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Deleted!', 'Student enrollment removed.', 'success').then(() => {
                                        table.ajax.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', data.message || 'Failed to delete enrollment.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Delete AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to delete enrollment.', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); // Flush output buffer ?>