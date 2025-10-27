<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Fetch students for a semester
    if ($action === 'fetch_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $query = "SELECT si.id, si.first_name, si.last_name, si.gr_no, si.enrollment_no, si.class_info_id, ci.classname, ci.batch 
                  FROM student_info si 
                  LEFT JOIN class_info ci ON si.class_info_id = ci.id 
                  WHERE si.sem_info_id = ? 
                  ORDER BY si.enrollment_no";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $students = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }

        echo json_encode(['status' => 'success', 'students' => $students]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    // Fetch regular classes for a semester
    if ($action === 'fetch_classes') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $query = "SELECT id, classname, batch 
                  FROM class_info 
                  WHERE sem_info_id = ?
                  ORDER BY classname, batch";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $classes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }

        echo json_encode(['status' => 'success', 'classes' => $classes]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    // Save class allocations
    if ($action === 'save_allocations') {
        $allocations = isset($_POST['allocations']) ? json_decode($_POST['allocations'], true) : [];
        if (empty($allocations)) {
            echo json_encode(['status' => 'error', 'message' => 'No allocations provided']);
            exit;
        }

        $success_count = 0;
        $query = "UPDATE student_info SET class_info_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);

        foreach ($allocations as $alloc) {
            $student_id = intval($alloc['student_id']);
            $class_id = !empty($alloc['class_id']) ? intval($alloc['class_id']) : null;

            mysqli_stmt_bind_param($stmt, 'ii', $class_id, $student_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success_count === count($allocations)) {
            echo json_encode(['status' => 'success', 'message' => 'All allocations saved successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Some allocations failed to save']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Class Allocation</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #student-table {
            border-collapse: collapse;
        }
        #student-table th,
        #student-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            /* gray-300 */
        }
        #student-table th {
            background-color: #374151;
            /* gray-700 */
            color: #ffffff;
            /* white */
        }
        #student-table tbody tr:hover {
            background-color: #f9fafb;
            /* gray-50 */
        }
        select.class-dropdown {
            width: 150px;
            padding: 4px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Student Class Allocation";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                <div class="w-full md:w-1/3">
                    <label for="semester" class="block text-gray-700 font-bold mb-2">Semester & Program</label>
                    <select id="semester" name="semester" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="" disabled selected>Select Semester & Program</option>
                        <?php
                        $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
                        $sem_result = mysqli_query($conn, $sem_query);
                        while ($row = mysqli_fetch_assoc($sem_result)) {
                            echo "<option value='{$row['id']}'>SEM {$row['sem']} - " . strtoupper($row['edu_type']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="p-6 bg-white rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <button id="save-btn" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-cyan-600 transition-all" disabled>Save Changes</button>
                    <input type="search" id="search-student" class="w-64 p-2 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" placeholder="Search student name..." aria-controls="student-table">
                </div>
                <table id="student-table" class="min-w-full bg-white shadow-lg rounded-md border border-gray-300">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2 rounded-tl-md">No</th>
                            <th class="border px-4 py-2">Student Name</th>
                            <th class="border px-4 py-2">Enrollment No</th>
                            <th class="border px-4 py-2">GR No</th>
                            <th class="border px-4 py-2 rounded-tr-md">Class</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let selectedSemId = '';
            let classes = [];
            let changedAllocations = {};

            const table = $('#student-table').DataTable({
                paging: false,
                info: false,
                searching: false,
                ordering: false,
                language: {
                    emptyTable: 'Please select a semester to view students'
                },
                columns: [
                    { data: 'no' },
                    { data: 'student_name' },
                    { data: 'enrollment_no' },
                    { data: 'gr_no' },
                    { data: 'class' }
                ]
            });

            // Debug: Log column headers to verify index
            console.log('Column headers:', table.columns().header().toArray().map(h => $(h).text()));

            // Real-time search for student name
            function bindSearch() {
                $('#search-student').off('input').on('input', function() {
                    const searchValue = $(this).val();
                    console.log('Search value:', searchValue); // Debug: Log search input
                    table.column(1).search(searchValue, false, true).draw();
                    console.log('Filtered rows:', table.rows({ search: 'applied' }).data().toArray()); // Debug: Log filtered data
                });
            }
            bindSearch();

            // Semester change: Load students and classes
            $('#semester').change(function() {
                selectedSemId = $(this).val();
                table.clear().draw();
                $('#save-btn').prop('disabled', true);
                changedAllocations = {};
                $('#search-student').val(''); // Clear search input
                table.column(1).search('').draw(); // Clear search filter

                if (selectedSemId) {
                    // Fetch classes
                    $.ajax({
                        url: 'student_class_allocation.php',
                        method: 'POST',
                        data: { action: 'fetch_classes', sem_info_id: selectedSemId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                classes = response.classes;
                                // Fetch students
                                $.ajax({
                                    url: 'student_class_allocation.php',
                                    method: 'POST',
                                    data: { action: 'fetch_students', sem_info_id: selectedSemId },
                                    dataType: 'json',
                                    success: function(response) {
                                        if (response.status === 'success') {
                                            table.clear();
                                            if (response.students.length === 0) {
                                                table.draw();
                                                return;
                                            }

                                            const rows = response.students.map((student, index) => {
                                                let classOptions = '<option value="">Select Class</option>';
                                                classes.forEach(cls => {
                                                    const selected = cls.id == student.class_info_id ? 'selected' : '';
                                                    classOptions += `<option value="${cls.id}" ${selected}>${cls.classname} - ${cls.batch.toUpperCase()}</option>`;
                                                });

                                                return {
                                                    no: index + 1,
                                                    student_name: `${student.first_name} ${student.last_name}`,
                                                    enrollment_no: student.enrollment_no,
                                                    gr_no: student.gr_no,
                                                    class: `<select class="class-dropdown" data-student-id="${student.id}" data-original-class="${student.class_info_id || ''}">
                                                                ${classOptions}
                                                            </select>`
                                                };
                                            });
                                            table.rows.add(rows).draw();
                                            console.log('Table data:', table.rows().data().toArray()); // Debug: Log table data

                                            // Rebind search after table draw
                                            bindSearch();

                                            // Track changes in class dropdowns
                                            $('.class-dropdown').on('change', function() {
                                                const studentId = $(this).data('student-id');
                                                const newClassId = $(this).val();
                                                const originalClassId = $(this).data('original-class');

                                                if (newClassId !== originalClassId) {
                                                    changedAllocations[studentId] = newClassId || null;
                                                } else {
                                                    delete changedAllocations[studentId];
                                                }

                                                $('#save-btn').prop('disabled', Object.keys(changedAllocations).length === 0);
                                            });
                                        } else {
                                            Swal.fire('Error', response.message || 'Failed to load students.', 'error');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('fetch_students AJAX error:', status, error, 'Response:', xhr.responseText);
                                        Swal.fire('Error', 'Failed to load students. Check the console for details.', 'error');
                                    }
                                });
                            } else {
                                Swal.fire('Error', response.message || 'Failed to load classes.', 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('fetch_classes AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load classes. Check the console for details.', 'error');
                        }
                    });
                }
            });

            // Save changes with confirmation
            $('#save-btn').click(function() {
                const allocations = Object.keys(changedAllocations).map(studentId => ({
                    student_id: studentId,
                    class_id: changedAllocations[studentId]
                }));

                if (allocations.length === 0) {
                    Swal.fire('Info', 'No changes to save.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to save the class allocations?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No',
                    confirmButtonColor: '#06b6d4', // cyan-500
                    cancelButtonColor: '#6b7280' // gray-500
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'student_class_allocation.php',
                            method: 'POST',
                            data: {
                                action: 'save_allocations',
                                allocations: JSON.stringify(allocations)
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: response.message,
                                        icon: 'success'
                                    }).then(() => {
                                        $('#semester').trigger('change');
                                    });
                                } else {
                                    Swal.fire('Error', response.message || 'Failed to save allocations.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('save_allocations AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error', 'Failed to save allocations. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>