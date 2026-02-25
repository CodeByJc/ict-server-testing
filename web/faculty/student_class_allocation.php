<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Fetch students for a semester + batch
    if ($action === 'fetch_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $batch_info_id = isset($_POST['batch_info_id']) ? intval($_POST['batch_info_id']) : 0;

        if ($sem_id <= 0 || $batch_info_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester or batch ID']);
            exit;
        }

        $query = "SELECT si.id, si.first_name, si.last_name, si.gr_no, si.enrollment_no,
                         si.class_info_id, ci.classname, ci.batch,
                         bi.batch_start_year, bi.batch_end_year
                  FROM student_info si
                  LEFT JOIN class_info ci ON si.class_info_id = ci.id
                  LEFT JOIN batch_info bi ON si.batch_info_id = bi.id
                  WHERE si.sem_info_id = ? AND si.batch_info_id = ?
                  ORDER BY si.enrollment_no";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $batch_info_id);
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

    // Fetch all batch info
    if ($action === 'fetch_batches') {
        $query = "SELECT id, batch_start_year, batch_end_year, edu_type FROM batch_info ORDER BY batch_start_year DESC";
        $result = mysqli_query($conn, $query);
        $batches = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $batches[] = $row;
        }
        echo json_encode(['status' => 'success', 'batches' => $batches]);
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
        }

        #student-table th {
            background-color: #374151;
            color: #ffffff;
        }

        #student-table tbody tr:hover {
            background-color: #f9fafb;
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
            <div class="bg-white p-6 rounded-xl shadow-md mb-6 flex space-x-4">
                <div class="w-1/3">
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

                <div class="w-1/3">
                    <label for="batch" class="block text-gray-700 font-bold mb-2">Select Batch</label>
                    <select id="batch" name="batch" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="" disabled selected>Select Batch</option>
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
                            <th>No</th>
                            <th>Student Name</th>
                            <th>Enrollment No</th>
                            <th>GR No</th>
                            <th>Batch Start Year</th>
                            <th>Batch End Year</th>
                            <th>Class</th>
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
            let selectedBatchId = '';
            let classes = [];
            let changedAllocations = {};

            const table = $('#student-table').DataTable({
                paging: false,
                info: false,
                searching: false,
                ordering: false,
                language: {
                    emptyTable: 'Please select a semester and batch to view students'
                },
                columns: [
                    { data: 'no' },
                    { data: 'student_name' },
                    { data: 'enrollment_no' },
                    { data: 'gr_no' },
                    { data: 'batch_start_year' },
                    { data: 'batch_end_year' },
                    { data: 'class' }
                ]
            });

            // Load all batches
            $.ajax({
                url: 'student_class_allocation.php',
                method: 'POST',
                data: { action: 'fetch_batches' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        response.batches.forEach(batch => {
                            $('#batch').append(`<option value="${batch.id}">${batch.batch_start_year} - ${batch.batch_end_year} (${batch.edu_type})</option>`);
                        });
                    }
                }
            });

            // Reload when semester or batch changes
            $('#semester, #batch').change(function() {
                selectedSemId = $('#semester').val();
                selectedBatchId = $('#batch').val();
                if (selectedSemId && selectedBatchId) loadStudents();
            });

            function loadStudents() {
                table.clear().draw();
                $('#save-btn').prop('disabled', true);
                changedAllocations = {};

                $.ajax({
                    url: 'student_class_allocation.php',
                    method: 'POST',
                    data: { action: 'fetch_classes', sem_info_id: selectedSemId },
                    dataType: 'json',
                    success: function(classResponse) {
                        if (classResponse.status === 'success') {
                            classes = classResponse.classes;
                            $.ajax({
                                url: 'student_class_allocation.php',
                                method: 'POST',
                                data: { action: 'fetch_students', sem_info_id: selectedSemId, batch_info_id: selectedBatchId },
                                dataType: 'json',
                                success: function(studentResponse) {
                                    if (studentResponse.status === 'success') {
                                        const rows = studentResponse.students.map((student, index) => {
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
                                                batch_start_year: student.batch_start_year || '-',
                                                batch_end_year: student.batch_end_year || '-',
                                                class: `<select class="class-dropdown" data-student-id="${student.id}" data-original-class="${student.class_info_id || ''}">
                                                            ${classOptions}
                                                        </select>`
                                            };
                                        });
                                        table.rows.add(rows).draw();

                                        $('.class-dropdown').on('change', function() {
                                            const studentId = $(this).data('student-id');
                                            const newClassId = $(this).val();
                                            const originalClassId = $(this).data('original-class');
                                            if (newClassId !== originalClassId) changedAllocations[studentId] = newClassId || null;
                                            else delete changedAllocations[studentId];
                                            $('#save-btn').prop('disabled', Object.keys(changedAllocations).length === 0);
                                        });
                                    }
                                }
                            });
                        }
                    }
                });
            }

            $('#save-btn').click(function() {
                const allocations = Object.keys(changedAllocations).map(studentId => ({
                    student_id: studentId,
                    class_id: changedAllocations[studentId]
                }));
                if (!allocations.length) return;

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to save the class allocations?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then(result => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'student_class_allocation.php',
                            method: 'POST',
                            data: { action: 'save_allocations', allocations: JSON.stringify(allocations) },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire('Saved!', response.message, 'success').then(() => loadStudents());
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>