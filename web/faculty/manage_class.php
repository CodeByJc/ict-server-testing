<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Function to fetch class information based on selected semId
function fetchClassInfo($sem_id)
{
    global $conn;
    $class_query = "
        SELECT 
            cl.id AS class_id,
            cl.classname,
            cl.batch,
            cl.group,
            cl.elective_subject_id,
            si.subject_name AS elective_subject_name,
            fi.id AS faculty_id,
            CONCAT(fi.first_name, ' ', fi.last_name) AS faculty_name
        FROM 
            class_info cl
        JOIN 
            faculty_info fi ON cl.faculty_info_id = fi.id
        LEFT JOIN
            subject_info si ON cl.elective_subject_id = si.id
        WHERE 
            cl.sem_info_id = ?
    ";

    $stmt = mysqli_prepare($conn, $class_query);
    mysqli_stmt_bind_param($stmt, 'i', $sem_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $classes = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $classes;
}

// Function to fetch faculty names
function fetchFacultyNames()
{
    global $conn;
    $faculty_query = "SELECT id, CONCAT(first_name, ' ', last_name) AS faculty_name FROM faculty_info";
    $result = mysqli_query($conn, $faculty_query);
    $faculty = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $faculty[] = $row;
        }
    }

    return $faculty;
}

// Function to fetch elective subjects for a semester
function fetchElectiveSubjects($sem_id)
{
    global $conn;
    $query = "SELECT id, subject_name FROM subject_info WHERE sem_info_id = ? AND type = 'elective' ORDER BY subject_name";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $sem_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subjects = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $subjects;
}

// Handle password verification for class deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_password') {
    $password = $_POST['password'];
    $userdata = isset($_SESSION['userdata']) ? $_SESSION['userdata'] : null;
    $session_faculty_id = isset($userdata['faculty_id']) ? $userdata['faculty_id'] : (isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : null);

    if (!$session_faculty_id) {
        echo json_encode(['status' => 'error', 'message' => 'No faculty session found, please log in.']);
        exit;
    }

    $admin_query = "SELECT password FROM user_login WHERE username = ?";
    $stmt = mysqli_prepare($conn, $admin_query);
    mysqli_stmt_bind_param($stmt, 's', $session_faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($admin && password_verify($password, $admin['password'])) {
        echo json_encode(['status' => 'success', 'message' => 'Password verified']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    }
    exit;
}

// Handle fetch elective subjects AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_elective_subjects') {
    $sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;
    if ($sem_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
        exit;
    }

    $subjects = fetchElectiveSubjects($sem_id);
    echo json_encode(['status' => 'success', 'subjects' => $subjects]);
    exit;
}

// Insert or update class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['classname'])) {
    $classname = strtoupper($_POST['classname']);
    $sem_id = $_POST['sem_id'];
    $batch = strtolower($_POST['batch']);
    $faculty_id = $_POST['faculty_id'];
    $group = strtolower($_POST['group']);
    $elective_subject_id = ($group === 'elective' && !empty($_POST['elective_subject_id'])) ? intval($_POST['elective_subject_id']) : null;
    $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : null;

    if ($group === 'elective' && $elective_subject_id === null) {
        echo json_encode(['status' => 'error', 'message' => 'Elective subject is required for elective group']);
        exit;
    }

    if ($class_id) {
        $update_query = "UPDATE class_info SET classname=?, sem_info_id=?, batch=?, faculty_info_id=?, `group`=?, elective_subject_id=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, 'sssissi', $classname, $sem_id, $batch, $faculty_id, $group, $elective_subject_id, $class_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Class updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating class. Please try again.']);
        }
        mysqli_stmt_close($stmt);
    } else {
        $insert_query = "INSERT INTO class_info (classname, sem_info_id, batch, faculty_info_id, `group`, elective_subject_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'sssiss', $classname, $sem_id, $batch, $faculty_id, $group, $elective_subject_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Class created successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error creating class. Please try again.']);
        }
        mysqli_stmt_close($stmt);
    }
    exit;
}

// Check if AJAX request is made for deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $class_id = $_POST['class_id'];
    $delete_query = "DELETE FROM class_info WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, 'i', $class_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Class deleted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting class. Please try again.']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Check if AJAX request is made for class info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sem_id'])) {
    $sem_id = $_POST['sem_id'];
    $classes = fetchClassInfo($sem_id);
    echo json_encode(['classes' => $classes]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>

    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Manage Class";
        include('./navbar.php');
        ?>

        <div class="p-5">
            <!-- Degree Semester Tabs -->
            <div id="degree-tabs" class="bg-white shadow-xl rounded-xl p-3 mb-4">
                <h3 class="text-md font-bold pl-5 pt-2 mb-2">Degree Semesters</h3>
                <div class="flex border-b">
                    <?php
                    $sem_query = "SELECT id, sem, edu_type FROM sem_info WHERE edu_type = 'Degree'";
                    $sem_result = mysqli_query($conn, $sem_query);
                    $first_degree = true;

                    if ($sem_result && mysqli_num_rows($sem_result) > 0) {
                        while ($sem_row = mysqli_fetch_assoc($sem_result)) {
                            echo "
                            <button class='sem-tab-button px-4 py-2 -mb-px border-b-2 " . ($first_degree ? 'border-cyan-500 text-cyan-500' : 'border-transparent text-gray-600') . " hover:text-cyan-500 hover:border-cyan-500' 
                                data-sem-id='{$sem_row['id']}' data-tab='sem-tab-{$sem_row['id']}'>
                                Sem {$sem_row['sem']}
                            </button>";
                            $first_degree = false;
                        }
                    } else {
                        echo "<p class='pl-5 text-gray-600'>No Degree semesters available or query failed</p>";
                    }
                    ?>
                </div>
            </div>

            <!-- Diploma Semester Tabs -->
            <div id="diploma-tabs" class="bg-white shadow-xl rounded-xl p-3 mb-4">
                <h3 class="text-md font-bold pl-5 pt-2 mb-2">Diploma Semesters</h3>
                <div class="flex border-b">
                    <?php
                    $sem_query = "SELECT id, sem, edu_type FROM sem_info WHERE edu_type = 'Diploma'";
                    $sem_result = mysqli_query($conn, $sem_query);
                    $first_diploma = true;

                    if ($sem_result && mysqli_num_rows($sem_result) > 0) {
                        while ($sem_row = mysqli_fetch_assoc($sem_result)) {
                            echo "
                            <button class='sem-tab-button px-4 py-2 -mb-px border-b-2 " . ($first_diploma && !$first_degree ? 'border-cyan-500 text-cyan-500' : 'border-transparent text-gray-600') . " hover:text-cyan-500 hover:border-cyan-500' 
                                data-sem-id='{$sem_row['id']}' data-tab='sem-tab-{$sem_row['id']}'>
                                Sem {$sem_row['sem']}
                            </button>";
                            $first_diploma = false;
                        }
                    } else {
                        echo "<p class='pl-5 text-gray-600'>No Diploma semesters available or query failed</p>";
                    }
                    ?>
                </div>
            </div>

            <!-- Class Tabs Container -->
            <div id="class-tabs-container" class="bg-white shadow-xl rounded-xl p-3">
                <div id="class-tabs"></div>
            </div>
        </div>
    </div>

    <div id="create-class-popup" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white p-8 rounded-lg w-4/12">
            <h3 class="text-xl font-bold mb-4" id="popup-title">Create New Class</h3>
            <form id="create-class-form" action="manage_class.php" method="POST">
                <input type="hidden" name="sem_id" id="popup-sem-id">
                <input type="hidden" name="class_id" id="popup-class-id">
                <div class="mb-4">
                    <label for="classname" class="block font-semibold">Class Name</label>
                    <input type="text" name="classname" id="classname" class="w-full p-2 border-2 rounded" required>
                </div>
                <div class="mb-4">
                    <label for="batch" class="block font-semibold">Batch</label>
                    <select name="batch" id="batch" class="w-full p-2 border-2 rounded" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="faculty_id" class="block font-semibold">Select Faculty</label>
                    <select name="faculty_id" id="faculty_id" class="w-full p-2 border-2 rounded" required>
                        <option value="" disabled selected>Select Faculty</option>
                        <?php
                        $faculty = fetchFacultyNames();
                        foreach ($faculty as $faculty_member) {
                            echo "<option value='{$faculty_member['id']}'>{$faculty_member['faculty_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="group" class="block font-semibold">Group</label>
                    <select name="group" id="group" class="w-full p-2 border-2 rounded" required>
                        <option value="regular">Regular</option>
                        <option value="elective">Elective</option>
                    </select>
                </div>
                <div class="mb-4 hidden" id="elective-subject-container">
                    <label for="elective_subject_id" class="block font-semibold">Elective Subject</label>
                    <select name="elective_subject_id" id="elective_subject_id" class="w-full p-2 border-2 rounded">
                        <option value="" disabled selected>Select Elective Subject</option>
                    </select>
                </div>
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 text-white p-2 rounded-full">Cancel</button>
                    <button type="submit" id="popup-submit" class="pl-6 pr-6 bg-cyan-500 text-white p-2 rounded-full">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Load classes for the first active semester on page load
            const firstSemTab = $('.sem-tab-button.border-cyan-500');
            if (firstSemTab.length) {
                const semId = firstSemTab.data('sem-id');
                fetchClassInfo(semId, $('#class-tabs')[0]);
            }

            // Semester tab switching for both Degree and Diploma
            $('.sem-tab-button').on('click', function() {
                const semId = $(this).data('sem-id');
                $('.sem-tab-button').removeClass('border-cyan-500 text-cyan-500').addClass('border-transparent text-gray-600');
                $(this).removeClass('border-transparent text-gray-600').addClass('border-cyan-500 text-cyan-500');
                fetchClassInfo(semId, $('#class-tabs')[0]);
            });

            // Handle group dropdown change
            $('#group').on('change', function() {
                const group = $(this).val();
                const semId = $('#popup-sem-id').val();
                const electiveContainer = $('#elective-subject-container');
                const electiveSelect = $('#elective_subject_id');

                if (group === 'elective' && semId) {
                    // Fetch elective subjects
                    $.ajax({
                        url: '',
                        type: 'POST',
                        data: {
                            action: 'fetch_elective_subjects',
                            sem_id: semId
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                electiveSelect.empty();
                                electiveSelect.append('<option value="" disabled selected>Select Elective Subject</option>');
                                if (result.subjects.length > 0) {
                                    result.subjects.forEach(subject => {
                                        electiveSelect.append(`<option value="${subject.id}">${subject.subject_name}</option>`);
                                    });
                                    electiveContainer.removeClass('hidden');
                                    electiveSelect.prop('required', true);
                                } else {
                                    electiveSelect.append('<option value="" disabled>No elective subjects available</option>');
                                    electiveContainer.removeClass('hidden');
                                    electiveSelect.prop('required', false);
                                }
                            } else {
                                Swal.fire('Error', result.message || 'Failed to fetch elective subjects.', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Unable to fetch elective subjects. Please try again.', 'error');
                        }
                    });
                } else {
                    electiveContainer.addClass('hidden');
                    electiveSelect.prop('required', false);
                    electiveSelect.empty().append('<option value="" disabled selected>Select Elective Subject</option>');
                }
            });
        });

        function fetchClassInfo(semId, tabContainer) {
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    sem_id: semId
                },
                success: function(response) {
                    const responseData = JSON.parse(response);
                    const $tabContainer = $(tabContainer);

                    $tabContainer.empty();

                    let tabsHtml = '<div class="flex border-b">';
                    if (responseData.classes.length > 0) {
                        responseData.classes.forEach((classItem, index) => {
                            tabsHtml += `
                            <button class="class-tab-button px-4 py-2 -mb-px border-b-2 ${index === 0 ? 'border-cyan-500 text-cyan-500' : 'border-transparent text-gray-600'} hover:text-cyan-500 hover:border-cyan-500" data-tab="tab-${classItem.class_id}">
                                ${classItem.classname} - ${classItem.batch.toUpperCase()}
                            </button>`;
                        });
                    }
                    // Add Create Class tab
                    tabsHtml += `
                    <button class="class-tab-button px-4 py-2 -mb-px border-b-2 border-transparent text-gray-600 hover:text-cyan-500 hover:border-cyan-500" data-tab="tab-create-${semId}">
                        + New
                    </button>
                </div>`;

                    // Tab content
                    let contentHtml = '<div class="tab-content mt-4">';
                    if (responseData.classes.length > 0) {
                        responseData.classes.forEach((classItem, index) => {
                            contentHtml += `
                            <div id="tab-${classItem.class_id}" class="tab-pane ${index === 0 ? '' : 'hidden'} p-4 bg-gray-50 rounded-b-xl">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-md text-gray-600 font-semibold">
                                        ${classItem.classname} - ${classItem.batch.toUpperCase()} - ${classItem.faculty_name} 
                                        (${classItem.group.charAt(0).toUpperCase() + classItem.group.slice(1)}${classItem.group === 'elective' && classItem.elective_subject_name ? ' - ' + classItem.elective_subject_name : ''})
                                    </h3>
                                    <div>
                                        <button onclick="editClass(${classItem.class_id}, '${classItem.classname}', '${classItem.batch}', ${classItem.faculty_id}, ${semId}, '${classItem.group}', ${classItem.elective_subject_id || 'null'})" 
                                            class="border-blue-500 text-sm font-bold border-2 border-blue-600 text-blue-600 px-4 py-1 rounded-full hover:bg-blue-600 hover:text-white mr-2">
                                            Edit
                                        </button>
                                        <button onclick="deleteClass(${classItem.class_id}, ${semId})" 
                                            class="border-red-500 text-sm font-bold border-2 border-red-600 text-red-600 px-4 py-1 rounded-full hover:bg-red-600 hover:text-white">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>`;
                        });
                    } else {
                        contentHtml += `<div id="tab-no-classes-${semId}" class="tab-pane hidden p-4 bg-gray-50 rounded-b-xl">
                        <p class="text-sm text-gray-500">No classes found for this semester.</p>
                    </div>`;
                    }
                    contentHtml += `
                    <div id="tab-create-${semId}" class="tab-pane ${responseData.classes.length === 0 ? '' : 'hidden'} p-4 bg-gray-50 rounded-b-xl">
                        <button class="transition-all bg-gray-100 drop-shadow-lg text-green-600 font-bold text-sm px-4 py-1 rounded-lg hover:scale-110" 
                            onclick="openCreateClassPopup(${semId})">
                            Create New Class
                        </button>
                    </div>
                </div>`;

                    $tabContainer.html(tabsHtml + contentHtml);

                    // Class tab switching logic
                    $tabContainer.find('.class-tab-button').on('click', function() {
                        const tabId = $(this).data('tab');
                        $tabContainer.find('.class-tab-button').removeClass('border-cyan-500 text-cyan-500').addClass('border-transparent text-gray-600');
                        $(this).removeClass('border-transparent text-gray-600').addClass('border-cyan-500 text-cyan-500');
                        $tabContainer.find('.tab-pane').addClass('hidden');
                        $tabContainer.find(`#${tabId}`).removeClass('hidden');
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Unable to fetch class info. Please try again.', 'error');
                }
            });
        }

        function deleteClass(classId, semId) {
            var verified = false;
            Swal.fire({
                title: 'Enter your password',
                input: 'password',
                inputLabel: 'Password',
                inputPlaceholder: 'Enter your password',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                confirmButtonText: 'Verify',
                showLoaderOnConfirm: true,
                preConfirm: (password) => {
                    return $.ajax({
                        url: '',
                        type: 'POST',
                        data: {
                            action: 'verify_password',
                            password: password
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.status === 'error') {
                                Swal.showValidationMessage(result.message);
                                verified = false;
                                return result;
                            }
                            verified = true;
                            return result;
                        },
                        error: function() {
                            Swal.showValidationMessage('Unable to verify password. Please try again.');
                            verified = false;
                            return result;
                        }
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then(result => {
                if (verified) {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'This will permanently delete the class.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'No, cancel'
                    }).then((confirmResult) => {
                        if (confirmResult.isConfirmed) {
                            $.ajax({
                                url: '',
                                type: 'POST',
                                data: {
                                    action: 'delete',
                                    class_id: classId
                                },
                                success: function(response) {
                                    const result = JSON.parse(response);
                                    Swal.fire({
                                        title: result.status === 'success' ? 'Deleted!' : 'Error',
                                        text: result.message,
                                        icon: result.status,
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        if (result.status === 'success') {
                                            window.location.reload();
                                        }
                                    });
                                },
                                error: function() {
                                    Swal.fire('Error', 'Unable to delete class. Please try again.', 'error');
                                }
                            });
                        }
                    });
                }
            });
        }

        function editClass(classId, classname, batch, facultyId, semId, group, electiveSubjectId) {
            document.getElementById('popup-class-id').value = classId;
            document.getElementById('classname').value = classname;
            document.getElementById('batch').value = batch.toUpperCase();
            document.getElementById('faculty_id').value = facultyId;
            document.getElementById('popup-sem-id').value = semId;
            document.getElementById('group').value = group;
            document.getElementById('popup-title').textContent = 'Edit Class';
            document.getElementById('popup-submit').textContent = 'Update';
            
            // Trigger group change to populate elective subjects
            const electiveContainer = $('#elective-subject-container');
            const electiveSelect = $('#elective_subject_id');
            if (group === 'elective') {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'fetch_elective_subjects',
                        sem_id: semId
                    },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            electiveSelect.empty();
                            electiveSelect.append('<option value="" disabled>Select Elective Subject</option>');
                            if (result.subjects.length > 0) {
                                result.subjects.forEach(subject => {
                                    const selected = subject.id == electiveSubjectId ? 'selected' : '';
                                    electiveSelect.append(`<option value="${subject.id}" ${selected}>${subject.subject_name}</option>`);
                                });
                                electiveContainer.removeClass('hidden');
                                electiveSelect.prop('required', true);
                            } else {
                                electiveSelect.append('<option value="" disabled>No elective subjects available</option>');
                                electiveContainer.removeClass('hidden');
                                electiveSelect.prop('required', false);
                            }
                        } else {
                            Swal.fire('Error', result.message || 'Failed to fetch elective subjects.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Unable to fetch elective subjects. Please try again.', 'error');
                    }
                });
            } else {
                electiveContainer.addClass('hidden');
                electiveSelect.prop('required', false);
                electiveSelect.empty().append('<option value="" disabled selected>Select Elective Subject</option>');
            }

            document.getElementById('create-class-popup').classList.remove('hidden');
        }

        function openCreateClassPopup(semId) {
            document.getElementById('popup-sem-id').value = semId;
            document.getElementById('popup-class-id').value = '';
            document.getElementById('classname').value = '';
            document.getElementById('batch').value = 'A';
            document.getElementById('faculty_id').value = '';
            document.getElementById('group').value = 'regular';
            document.getElementById('popup-title').textContent = 'Create New Class';
            document.getElementById('popup-submit').textContent = 'Create';
            $('#elective-subject-container').addClass('hidden');
            $('#elective_subject_id').prop('required', false).empty().append('<option value="" disabled selected>Select Elective Subject</option>');
            document.getElementById('create-class-popup').classList.remove('hidden');
        }

        function closePopup() {
            document.getElementById('create-class-popup').classList.add('hidden');
            $('#create-class-form')[0].reset();
            $('#elective-subject-container').addClass('hidden');
            $('#elective_subject_id').prop('required', false).empty().append('<option value="" disabled selected>Select Elective Subject</option>');
        }

        $('#create-class-form').submit(function(e) {
            e.preventDefault();
            $.ajax({
                url: '',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    const result = JSON.parse(response);
                    Swal.fire(result.status === 'success' ? 'Success' : 'Error', result.message, result.status);
                    if (result.status === 'success') {
                        closePopup();
                        fetchClassInfo($('#popup-sem-id').val(), $('#class-tabs')[0]);
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Unable to process request. Please try again.', 'error');
                }
            });
        });
    </script>
</body>

</html>