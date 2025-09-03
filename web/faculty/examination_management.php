<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Fetch exams for a semester
    if ($action === 'fetch_exams') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $query = "SELECT e.id, e.exam_type, e.exam_date, s.subject_name 
                  FROM examination e 
                  INNER JOIN subject_info s ON e.subject_info_id = s.id 
                  WHERE e.sem_info_id = ? 
                  ORDER BY s.subject_name, e.exam_type";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exams = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $exams[] = $row;
        }

        echo json_encode(['status' => 'success', 'exams' => $exams]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    // Fetch subjects for a semester
    if ($action === 'fetch_subjects') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $query = "SELECT id, subject_name 
                  FROM subject_info 
                  WHERE sem_info_id = ? 
                  ORDER BY subject_name";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $subjects = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }

        echo json_encode(['status' => 'success', 'subjects' => $subjects]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    // Add an exam
    if ($action === 'add_exam') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        $exam_type = isset($_POST['exam_type']) ? $_POST['exam_type'] : '';
        $exam_date = isset($_POST['exam_date']) ? $_POST['exam_date'] : '';

        if ($sem_id <= 0 || $subject_id <= 0 || !in_array($exam_type, ['mid1', 'mid2', 'final', 'viva']) || empty($exam_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            exit;
        }

        // Check for duplicate exam
        $query = "SELECT id FROM examination 
                  WHERE sem_info_id = ? AND subject_info_id = ? AND exam_type = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iis', $sem_id, $subject_id, $exam_type);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This exam already exists for the subject and semester']);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        mysqli_stmt_close($stmt);

        // Insert exam
        $query = "INSERT INTO examination (sem_info_id, subject_info_id, exam_type, exam_date) 
                  VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iiss', $sem_id, $subject_id, $exam_type, $exam_date);
        $success = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Exam added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add exam']);
        }
        exit;
    }

    // Edit an exam
    if ($action === 'edit_exam') {
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        $exam_type = isset($_POST['exam_type']) ? $_POST['exam_type'] : '';
        $exam_date = isset($_POST['exam_date']) ? $_POST['exam_date'] : '';
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;

        if ($exam_id <= 0 || $sem_id <= 0 || $subject_id <= 0 || !in_array($exam_type, ['mid1', 'mid2', 'final', 'viva']) || empty($exam_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            exit;
        }

        // Check for duplicate exam (excluding current exam)
        $query = "SELECT id FROM examination 
                  WHERE sem_info_id = ? AND subject_info_id = ? AND exam_type = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iisi', $sem_id, $subject_id, $exam_type, $exam_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This exam already exists for the subject and semester']);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        mysqli_stmt_close($stmt);

        // Update exam
        $query = "UPDATE examination 
                  SET exam_type = ?, exam_date = ? 
                  WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssi', $exam_type, $exam_date, $exam_id);
        $success = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Exam updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update exam']);
        }
        exit;
    }

    // Delete an exam
    if ($action === 'delete_exam') {
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        if ($exam_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid exam ID']);
            exit;
        }

        $query = "DELETE FROM examination WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $exam_id);
        $success = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Exam deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete exam']);
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
    <title>Examination Management</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #exam-table {
            border-collapse: collapse;
        }
        #exam-table th,
        #exam-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            /* gray-300 */
        }
        #exam-table th {
            background-color: #374151;
            /* gray-700 */
            color: #ffffff;
            /* white */
        }
        #exam-table tbody tr:hover {
            background-color: #f9fafb;
            /* gray-50 */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Examination Management";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                <div class="flex flex-col md:flex-row md:space-x-4">
                    <div class="w-full md:w-1/3 mb-4 md:mb-0">
                        <label for="semester" class="block text-gray-700 font-bold mb-2">Semester & Program</label>
                        <select id="semester" name="semester" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                            <option value="" disabled selected>Select Semester & Program</option>
                            <?php
                            $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
                            $sem_result = mysqli_query($conn, $sem_query);
                            while ($row = mysqli_fetch_assoc($sem_result)) {
                                echo "<option value='{$row['id']}'>SEM {$row['sem']} - " . strtoupper($row['edu_type']) . "</option>";
                            }
                            mysqli_close($conn);
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-6 bg-white rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <button id="add-exam-btn" class="bg-green-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-green-600 transition-all" disabled>Add Exam</button>
                </div>
                <table id="exam-table" class="min-w-full bg-white shadow-lg rounded-md border border-gray-300">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2 rounded-tl-md">Subject Name</th>
                            <th class="border px-4 py-2">Exam Type</th>
                            <th class="border px-4 py-2">Exam Date</th>
                            <th class="border px-4 py-2 rounded-tr-md">Actions</th>
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

            const table = $('#exam-table').DataTable({
                paging: false,
                info: false,
                searching: false,
                ordering: false,
                language: {
                    emptyTable: 'Please select a semester to view exams'
                },
                columns: [
                    { data: 'subject_name' },
                    { 
                        data: 'exam_type',
                        render: function(data) {
                            return data.toUpperCase().replace('MID', 'MID ');
                        }
                    },
                    { data: 'exam_date' },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `
                                <button class="edit-btn text-blue-500 transition-all mr-4" data-exam-id="${row.id}" data-subject-name="${row.subject_name}" data-exam-type="${row.exam_type}" data-exam-date="${row.exam_date}" title="Edit Exam">Edit</button>
                                <button class="delete-btn text-red-500 transition-all" data-exam-id="${row.id}" data-subject-name="${row.subject_name}" title="Delete Exam">Delete</button>
                            `;
                        }
                    }
                ]
            });

            // Semester change: Load exams
            $('#semester').change(function() {
                selectedSemId = $(this).val();
                $('#add-exam-btn').prop('disabled', !selectedSemId);
                table.clear().draw();

                if (selectedSemId) {
                    $.ajax({
                        url: 'examination_management.php',
                        method: 'POST',
                        data: { action: 'fetch_exams', sem_info_id: selectedSemId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                table.clear();
                                if (response.exams.length > 0) {
                                    table.rows.add(response.exams).draw();
                                } else {
                                    table.draw();
                                }
                            } else {
                                Swal.fire('Error', response.message || 'Failed to load exams.', 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('fetch_exams AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load exams. Check the console for details.', 'error');
                        }
                    });
                }
            });

            // Add exam pop-up
            $('#add-exam-btn').click(function() {
                $.ajax({
                    url: 'examination_management.php',
                    method: 'POST',
                    data: { action: 'fetch_subjects', sem_info_id: selectedSemId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            if (response.subjects.length === 0) {
                                Swal.fire('Info', 'No subjects found for this semester.', 'info');
                                return;
                            }

                            let subjectOptions = '<option value="" disabled selected>Select Subject</option>';
                            response.subjects.forEach(subject => {
                                subjectOptions += `<option value="${subject.id}">${subject.subject_name}</option>`;
                            });

                            Swal.fire({
                                title: 'Add Exam',
                                html: `
                                    <div class="text-left">
                                        <label for="subject-select" class="block text-gray-700 font-bold mb-2">Subject</label>
                                        <select id="subject-select" class="w-full p-3 border-2 rounded-xl mb-4">
                                            ${subjectOptions}
                                        </select>
                                        <label for="exam-type" class="block text-gray-700 font-bold mb-2">Exam Type</label>
                                        <select id="exam-type" class="w-full p-3 border-2 rounded-xl mb-4">
                                            <option value="" disabled selected>Select Exam Type</option>
                                            <option value="mid1">MID 1</option>
                                            <option value="mid2">MID 2</option>
                                            <option value="final">FINAL</option>
                                            <option value="viva">VIVA</option>
                                        </select>
                                        <label for="exam-date" class="block text-gray-700 font-bold mb-2">Exam Date</label>
                                        <input type="date" id="exam-date" class="w-full p-3 border-2 rounded-xl">
                                    </div>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Add',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#06b6d4',
                                cancelButtonColor: '#6b7280',
                                preConfirm: () => {
                                    const subjectId = $('#subject-select').val();
                                    const examType = $('#exam-type').val();
                                    const examDate = $('#exam-date').val();
                                    if (!subjectId || !examType || !examDate) {
                                        Swal.showValidationMessage('Please fill all fields.');
                                    }
                                    return { subjectId, examType, examDate };
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: 'examination_management.php',
                                        method: 'POST',
                                        data: {
                                            action: 'add_exam',
                                            sem_info_id: selectedSemId,
                                            subject_info_id: result.value.subjectId,
                                            exam_type: result.value.examType,
                                            exam_date: result.value.examDate
                                        },
                                        dataType: 'json',
                                        success: function(response) {
                                            if (response.status === 'success') {
                                                Swal.fire('Success', response.message, 'success').then(() => {
                                                    $('#semester').trigger('change');
                                                });
                                            } else {
                                                Swal.fire('Error', response.message || 'Failed to add exam.', 'error');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('add_exam AJAX error:', status, error, 'Response:', xhr.responseText);
                                            Swal.fire('Error', 'Failed to add exam. Check the console for details.', 'error');
                                        }
                                    });
                                }
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to load subjects.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('fetch_subjects AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to load subjects. Check the console for details.', 'error');
                    }
                });
            });

            // Edit exam pop-up
            $(document).on('click', '.edit-btn', function() {
                const examId = $(this).data('exam-id');
                const subjectName = $(this).data('subject-name');
                const examType = $(this).data('exam-type');
                const examDate = $(this).data('exam-date');
                const subjectId = table.row($(this).closest('tr')).data().subject_info_id; // Assuming subject_info_id is fetched in fetch_exams

                $.ajax({
                    url: 'examination_management.php',
                    method: 'POST',
                    data: { action: 'fetch_subjects', sem_info_id: selectedSemId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            let subjectOptions = '';
                            response.subjects.forEach(subject => {
                                const selected = subject.id == subjectId ? 'selected' : '';
                                subjectOptions += `<option value="${subject.id}" ${selected}>${subject.subject_name}</option>`;
                            });

                            Swal.fire({
                                title: 'Edit Exam',
                                html: `
                                    <div class="text-left">
                                        <label for="subject-select" class="block text-gray-700 font-bold mb-2">Subject</label>
                                        <select id="subject-select" class="w-full p-3 border-2 rounded-xl mb-4" disabled>
                                            ${subjectOptions}
                                        </select>
                                        <label for="exam-type" class="block text-gray-700 font-bold mb-2">Exam Type</label>
                                        <select id="exam-type" class="w-full p-3 border-2 rounded-xl mb-4">
                                            <option value="" disabled>Select Exam Type</option>
                                            <option value="mid1" ${examType === 'mid1' ? 'selected' : ''}>MID 1</option>
                                            <option value="mid2" ${examType === 'mid2' ? 'selected' : ''}>MID 2</option>
                                            <option value="final" ${examType === 'final' ? 'selected' : ''}>FINAL</option>
                                            <option value="viva" ${examType === 'viva' ? 'selected' : ''}>VIVA</option>
                                        </select>
                                        <label for="exam-date" class="block text-gray-700 font-bold mb-2">Exam Date</label>
                                        <input type="date" id="exam-date" value="${examDate}" class="w-full p-3 border-2 rounded-xl">
                                    </div>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Update',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#06b6d4',
                                cancelButtonColor: '#6b7280',
                                preConfirm: () => {
                                    const subjectId = $('#subject-select').val();
                                    const examType = $('#exam-type').val();
                                    const examDate = $('#exam-date').val();
                                    if (!subjectId || !examType || !examDate) {
                                        Swal.showValidationMessage('Please fill all fields.');
                                    }
                                    return { subjectId, examType, examDate };
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: 'examination_management.php',
                                        method: 'POST',
                                        data: {
                                            action: 'edit_exam',
                                            exam_id: examId,
                                            sem_info_id: selectedSemId,
                                            subject_info_id: result.value.subjectId,
                                            exam_type: result.value.examType,
                                            exam_date: result.value.examDate
                                        },
                                        dataType: 'json',
                                        success: function(response) {
                                            if (response.status === 'success') {
                                                Swal.fire('Success', response.message, 'success').then(() => {
                                                    $('#semester').trigger('change');
                                                });
                                            } else {
                                                Swal.fire('Error', response.message || 'Failed to update exam.', 'error');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('edit_exam AJAX error:', status, error, 'Response:', xhr.responseText);
                                            Swal.fire('Error', 'Failed to update exam. Check the console for details.', 'error');
                                        }
                                    });
                                }
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to load subjects.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('fetch_subjects AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to load subjects. Check the console for details.', 'error');
                    }
                });
            });

            // Delete exam
            $(document).on('click', '.delete-btn', function() {
                const examId = $(this).data('exam-id');
                const subjectName = $(this).data('subject-name');

                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you want to delete the exam for ${subjectName}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'No',
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'examination_management.php',
                            method: 'POST',
                            data: { action: 'delete_exam', exam_id: examId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire('Success', response.message, 'success').then(() => {
                                        $('#semester').trigger('change');
                                    });
                                } else {
                                    Swal.fire('Error', response.message || 'Failed to delete exam.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('delete_exam AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error', 'Failed to delete exam. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>