<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Fetch elective subjects for a semester
    if ($action === 'fetch_subjects') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $query = "SELECT id, subject_name 
                  FROM subject_info 
                  WHERE sem_info_id = ? AND type = 'elective' 
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

    // Fetch students allocated to an elective subject
    if ($action === 'fetch_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($sem_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester or subject ID']);
            exit;
        }

        $query = "SELECT si.id, si.first_name, si.last_name, si.gr_no, si.enrollment_no, ea.class_info_id, ci.classname, ci.batch 
                  FROM student_info si 
                  INNER JOIN elective_allocation ea ON si.id = ea.student_info_id 
                  LEFT JOIN class_info ci ON ea.class_info_id = ci.id 
                  WHERE si.sem_info_id = ? AND ea.subject_info_id = ? 
                  ORDER BY si.enrollment_no";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $subject_id);
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

    // Fetch classes for an elective subject
    if ($action === 'fetch_classes') {
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid subject ID']);
            exit;
        }

        $query = "SELECT id, classname, batch 
                  FROM class_info 
                  WHERE elective_subject_id = ? 
                  ORDER BY classname, batch";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $subject_id);
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

    // Fetch available students (not allocated to the subject)
    if ($action === 'fetch_available_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($sem_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester or subject ID']);
            exit;
        }

        $query = "SELECT si.id, si.first_name, si.last_name 
                  FROM student_info si 
                  WHERE si.sem_info_id = ? 
                  AND si.id NOT IN (
                      SELECT student_info_id 
                      FROM elective_allocation 
                      WHERE subject_info_id = ?
                  ) 
                  ORDER BY si.first_name, si.last_name";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $subject_id);
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

    // Add a student to an elective
    if ($action === 'add_student') {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : null;

        if ($student_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid student or subject ID']);
            exit;
        }

        if (!$class_id) {
            $query = "INSERT INTO elective_allocation (student_info_id, subject_info_id) 
                      VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ii', $student_id, $subject_id);
        } else {
            $query = "INSERT INTO elective_allocation (student_info_id, subject_info_id, class_info_id) 
                      VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'iii', $student_id, $subject_id, $class_id);
        }

        $success = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Student added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add student']);
        }
        exit;
    }

    // Delete a student from an elective
    if ($action === 'delete_student') {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($student_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid student or subject ID']);
            exit;
        }

        $query = "DELETE FROM elective_allocation 
                  WHERE student_info_id = ? AND subject_info_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $student_id, $subject_id);
        $success = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Student removed successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove student or student not found']);
        }
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
        $query = "UPDATE elective_allocation SET class_info_id = ? 
                  WHERE student_info_id = ? AND subject_info_id = ?";
        $stmt = mysqli_prepare($conn, $query);

        foreach ($allocations as $alloc) {
            $student_id = intval($alloc['student_id']);
            $subject_id = intval($alloc['subject_info_id']);
            $class_id = !empty($alloc['class_id']) ? intval($alloc['class_id']) : null;

            mysqli_stmt_bind_param($stmt, 'iii', $class_id, $student_id, $subject_id);
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
    <title>Student Elective Allocation</title>
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
        $page_title = "Student Elective Allocation";
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
                    <div class="w-full md:w-1/3">
                        <label for="elective-subject" class="block text-gray-700 font-bold mb-2">Elective Subject</label>
                        <select id="elective-subject" name="elective-subject" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" disabled>
                            <option value="" disabled selected>Select Elective Subject</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-6 bg-white rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex space-x-4">
                        <button id="save-btn" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-cyan-600 transition-all" disabled>Save Changes</button>
                        <button id="add-student-btn" class="bg-green-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-green-600 transition-all" disabled>Add Student</button>
                    </div>
                    <input type="text" id="search-student" class="w-64 p-2 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" placeholder="Search student name...">
                </div>
                <table id="student-table" class="min-w-full bg-white shadow-lg rounded-md border border-gray-300">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2 rounded-tl-md">No</th>
                            <th class="border px-4 py-2">Student Name</th>
                            <th class="border px-4 py-2">Enrollment No</th>
                            <th class="border px-4 py-2">GR No</th>
                            <th class="border px-4 py-2">Elective Class</th>
                            <th class="border px-4 py-2 rounded-tr-md">Action</th>
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
            let selectedSubjectId = '';
            let classes = [];
            let changedAllocations = {};

            const table = $('#student-table').DataTable({
                paging: false,
                info: false,
                searching: false,
                ordering: false,
                language: {
                    emptyTable: 'Please select a semester and elective subject to view students'
                },
                columns: [
                    { data: 'no' },
                    { data: 'student_name' },
                    { data: 'enrollment_no' },
                    { data: 'gr_no' },
                    { data: 'class' },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `<button class="delete-btn text-red-500 transition-all" data-student-id="${row.student_id}" data-subject-id="${selectedSubjectId}" title="Remove Student">
                                        Delete
                                    </button>`;
                        }
                    }
                ]
            });

            // Debug: Log column headers
            console.log('Column headers:', table.columns().header().toArray().map(h => $(h).text()));

            // Real-time search for student name
            function bindSearch() {
                $('#search-student').off('input').on('input', function() {
                    const searchValue = $(this).val();
                    console.log('Search value:', searchValue);
                    table.column(1).search(searchValue, false, true).draw();
                    console.log('Filtered rows:', table.rows({ search: 'applied' }).data().toArray());
                });
            }
            bindSearch();

            // Semester change: Load elective subjects
            $('#semester').change(function() {
                selectedSemId = $(this).val();
                selectedSubjectId = '';
                $('#elective-subject').prop('disabled', true).val('');
                $('#add-student-btn').prop('disabled', true);
                table.clear().draw();
                $('#save-btn').prop('disabled', true);
                changedAllocations = {};
                $('#search-student').val('');
                table.column(1).search('').draw();

                if (selectedSemId) {
                    $.ajax({
                        url: 'student_elective_allocation.php',
                        method: 'POST',
                        data: { action: 'fetch_subjects', sem_info_id: selectedSemId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#elective-subject').empty().append('<option value="" disabled selected>Select Elective Subject</option>');
                                if (response.subjects.length > 0) {
                                    response.subjects.forEach(subject => {
                                        $('#elective-subject').append(`<option value="${subject.id}">${subject.subject_name}</option>`);
                                    });
                                    $('#elective-subject').prop('disabled', false);
                                } else {
                                    Swal.fire('Info', 'No elective subjects found for this semester.', 'info');
                                }
                            } else {
                                Swal.fire('Error', response.message || 'Failed to load elective subjects.', 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('fetch_subjects AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load elective subjects. Check the console for details.', 'error');
                        }
                    });
                }
            });

            // Elective subject change: Load students and classes
            $('#elective-subject').change(function() {
                selectedSubjectId = $(this).val();
                table.clear().draw();
                $('#save-btn').prop('disabled', true);
                $('#add-student-btn').prop('disabled', !selectedSubjectId);
                changedAllocations = {};
                $('#search-student').val('');
                table.column(1).search('').draw();

                if (selectedSubjectId) {
                    // Fetch classes
                    $.ajax({
                        url: 'student_elective_allocation.php',
                        method: 'POST',
                        data: { action: 'fetch_classes', subject_info_id: selectedSubjectId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                classes = response.classes;
                                // Fetch students
                                $.ajax({
                                    url: 'student_elective_allocation.php',
                                    method: 'POST',
                                    data: { 
                                        action: 'fetch_students', 
                                        sem_info_id: selectedSemId, 
                                        subject_info_id: selectedSubjectId 
                                    },
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
                                                    student_id: student.id,
                                                    student_name: `${student.first_name} ${student.last_name}`,
                                                    enrollment_no: student.enrollment_no,
                                                    gr_no: student.gr_no,
                                                    class: `<select class="class-dropdown" data-student-id="${student.id}" data-subject-id="${selectedSubjectId}" data-original-class="${student.class_info_id || ''}">
                                                                ${classOptions}
                                                            </select>`
                                                };
                                            });
                                            table.rows.add(rows).draw();
                                            console.log('Table data:', table.rows().data().toArray());

                                            bindSearch();

                                            // Track changes in class dropdowns
                                            $('.class-dropdown').on('change', function() {
                                                const studentId = $(this).data('student-id');
                                                const subjectId = $(this).data('subject-id');
                                                const newClassId = $(this).val();
                                                const originalClassId = $(this).data('original-class');

                                                if (newClassId !== originalClassId) {
                                                    changedAllocations[`${studentId}_${subjectId}`] = {
                                                        student_id: studentId,
                                                        subject_info_id: subjectId,
                                                        class_id: newClassId || null
                                                    };
                                                } else {
                                                    delete changedAllocations[`${studentId}_${subjectId}`];
                                                }

                                                $('#save-btn').prop('disabled', Object.keys(changedAllocations).length === 0);
                                            });

                                            // Handle delete button click
                                            $('.delete-btn').on('click', function() {
                                                const studentId = $(this).data('student-id');
                                                const subjectId = $(this).data('subject-id');
                                                const studentName = table.row($(this).closest('tr')).data().student_name;

                                                Swal.fire({
                                                    title: 'Are you sure?',
                                                    text: `Do you want to remove ${studentName} from this elective?`,
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonText: 'Yes, remove',
                                                    cancelButtonText: 'No',
                                                    confirmButtonColor: '#06b6d4',
                                                    cancelButtonColor: '#6b7280'
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        $.ajax({
                                                            url: 'student_elective_allocation.php',
                                                            method: 'POST',
                                                            data: {
                                                                action: 'delete_student',
                                                                student_id: studentId,
                                                                subject_info_id: subjectId
                                                            },
                                                            dataType: 'json',
                                                            success: function(response) {
                                                                if (response.status === 'success') {
                                                                    Swal.fire('Success', response.message, 'success').then(() => {
                                                                        $('#elective-subject').trigger('change');
                                                                    });
                                                                } else {
                                                                    Swal.fire('Error', response.message || 'Failed to remove student.', 'error');
                                                                }
                                                            },
                                                            error: function(xhr, status, error) {
                                                                console.error('delete_student AJAX error:', status, error, 'Response:', xhr.responseText);
                                                                Swal.fire('Error', 'Failed to remove student. Check the console for details.', 'error');
                                                            }
                                                        });
                                                    }
                                                });
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

            // Add student pop-up
            $('#add-student-btn').click(function() {
                $.ajax({
                    url: 'student_elective_allocation.php',
                    method: 'POST',
                    data: { 
                        action: 'fetch_available_students', 
                        sem_info_id: selectedSemId, 
                        subject_info_id: selectedSubjectId 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            if (response.students.length === 0) {
                                Swal.fire('Info', 'No available students to add for this elective.', 'info');
                                return;
                            }

                            let studentOptions = '<option value="" disabled selected>Select Student</option>';
                            response.students.forEach(student => {
                                studentOptions += `<option value="${student.id}">${student.first_name} ${student.last_name}</option>`;
                            });

                            // Fetch classes for the subject
                            $.ajax({
                                url: 'student_elective_allocation.php',
                                method: 'POST',
                                data: { action: 'fetch_classes', subject_info_id: selectedSubjectId },
                                dataType: 'json',
                                success: function(classResponse) {
                                    if (classResponse.status === 'success') {
                                        let popupHtml = `
                                            <div class="text-left">
                                                <label for="student-select" class="block text-gray-700 font-bold mb-2">Student</label>
                                                <select id="student-select" class="w-full p-3 border-2 rounded-xl mb-4">
                                                    ${studentOptions}
                                                </select>
                                        `;
                                        let hasClasses = classResponse.classes.length > 0;
                                        let classOptions = '';

                                        if (hasClasses) {
                                            classOptions = '<option value="" disabled selected>Select Class</option>';
                                            classResponse.classes.forEach(cls => {
                                                classOptions += `<option value="${cls.id}">${cls.classname} - ${cls.batch.toUpperCase()}</option>`;
                                            });
                                            popupHtml += `
                                                <label for="class-select" class="block text-gray-700 font-bold mb-2">Class</label>
                                                <select id="class-select" class="w-full p-3 border-2 rounded-xl">
                                                    ${classOptions}
                                                </select>
                                            `;
                                        }
                                        popupHtml += `</div>`;

                                        Swal.fire({
                                            title: 'Add Student to Elective',
                                            html: popupHtml,
                                            showCancelButton: true,
                                            confirmButtonText: 'Add',
                                            cancelButtonText: 'Cancel',
                                            confirmButtonColor: '#06b6d4',
                                            cancelButtonColor: '#6b7280',
                                            preConfirm: () => {
                                                const studentId = $('#student-select').val();
                                                const classId = hasClasses ? $('#class-select').val() : null;
                                                if (!studentId) {
                                                    Swal.showValidationMessage('Please select a student.');
                                                    return false;
                                                }
                                                if (hasClasses && !classId) {
                                                    Swal.showValidationMessage('Please select a class.');
                                                    return false;
                                                }
                                                return { studentId, classId };
                                            }
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $.ajax({
                                                    url: 'student_elective_allocation.php',
                                                    method: 'POST',
                                                    data: {
                                                        action: 'add_student',
                                                        student_id: result.value.studentId,
                                                        subject_info_id: selectedSubjectId,
                                                        class_id: result.value.classId
                                                    },
                                                    dataType: 'json',
                                                    success: function(response) {
                                                        if (response.status === 'success') {
                                                            Swal.fire('Success', response.message, 'success').then(() => {
                                                                $('#elective-subject').trigger('change');
                                                            });
                                                        } else {
                                                            Swal.fire('Error', response.message || 'Failed to add student.', 'error');
                                                        }
                                                    },
                                                    error: function(xhr, status, error) {
                                                        console.error('add_student AJAX error:', status, error, 'Response:', xhr.responseText);
                                                        Swal.fire('Error', 'Failed to add student. Check the console for details.', 'error');
                                                    }
                                                });
                                            }
                                        });
                                    } else {
                                        Swal.fire('Error', classResponse.message || 'Failed to load classes.', 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('fetch_classes AJAX error:', status, error, 'Response:', xhr.responseText);
                                    Swal.fire('Error', 'Failed to load classes for pop-up. Check the console for details.', 'error');
                                }
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to load available students.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('fetch_available_students AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to load available students. Check the console for details.', 'error');
                    }
                });
            });

            // Save changes with confirmation
            $('#save-btn').click(function() {
                const allocations = Object.values(changedAllocations);
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
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'student_elective_allocation.php',
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
                                        $('#elective-subject').trigger('change');
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