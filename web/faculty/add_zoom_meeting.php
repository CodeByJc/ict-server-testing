<?php
// Start output buffering to prevent stray output
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../api/db/db_connection.php');

// Check database connection
if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'] ?? [];
$faculty_info_id = $userdata['id'] ?? 0; // Ensure $userdata['id'] is set

// Handle AJAX add/edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_ids'])) {
    header('Content-Type: application/json'); // Set JSON header

    $meeting_id = isset($_POST['meeting_id']) && !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null;
    $meeting_title = trim($_POST['meeting_title'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $meeting_date = trim($_POST['meeting_date'] ?? '');
    $meeting_time = trim($_POST['meeting_time'] ?? '');
    $sem_id = trim($_POST['semId'] ?? '');

    // Validation
    if (empty($meeting_title) || empty($meeting_link) || empty($meeting_date) || empty($meeting_time) || empty($sem_id)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        ob_end_flush();
        exit;
    }

    // Validate URL
    if (!filter_var($meeting_link, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid meeting link']);
        ob_end_flush();
        exit;
    }

    // Validate date and time formats
    if (!DateTime::createFromFormat('Y-m-d', $meeting_date) || !DateTime::createFromFormat('H:i', $meeting_time)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date or time format']);
        ob_end_flush();
        exit;
    }

    try {
        if ($meeting_id) {
            // Update existing meeting
            $stmt = $conn->prepare("UPDATE zoom_link_info 
                                    SET zoom_link_title = ?, zoom_date = ?, zoom_link_time = ?, zoom_link = ?, sem_info_id = ?
                                    WHERE id = ? AND faculty_info_id = ?");
            $stmt->bind_param("ssssiii", $meeting_title, $meeting_date, $meeting_time, $meeting_link, $sem_id, $meeting_id, $faculty_info_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Meeting updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update meeting: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Add new meeting
            $stmt = $conn->prepare("INSERT INTO zoom_link_info (zoom_link_title, zoom_date, zoom_link_time, zoom_link, faculty_info_id, sem_info_id) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $meeting_title, $meeting_date, $meeting_time, $meeting_link, $faculty_info_id, $sem_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Meeting added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add meeting: ' . $stmt->error]);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Handle multiple deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    header('Content-Type: application/json');
    $delete_ids = $_POST['delete_ids'];
    if (!empty($delete_ids)) {
        $placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
        $stmt = $conn->prepare("DELETE FROM zoom_link_info WHERE id IN ($placeholders) AND faculty_info_id = ?");
        $types = str_repeat('i', count($delete_ids)) . 'i';
        $params = array_merge($delete_ids, [$faculty_info_id]);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Meetings deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete meetings: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'No meetings selected']);
    }
    ob_end_flush();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meetings</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #meetings-table {
            border-collapse: collapse;
            width: 100%;
        }
        #meetings-table th,
        #meetings-table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: center;
        }
        #meetings-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #meetings-table tbody tr:hover {
            background-color: #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Meetings";
        include('./navbar.php');

        // Handle single delete request via GET
        if (isset($_GET['id'])) {
            $meeting_id = (int)$_GET['id'];
            $stmt = $conn->prepare("DELETE FROM zoom_link_info WHERE id = ? AND faculty_info_id = ?");
            $stmt->bind_param("ii", $meeting_id, $faculty_info_id);
            if ($stmt->execute()) {
                echo "<script>
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Meeting has been deleted.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => { window.location.href = 'add_zoom_meeting.php'; });
                </script>";
            } else {
                echo "<script>Swal.fire({title: 'Error!', text: 'Failed to delete meeting: " . $stmt->error . "', icon: 'error'});</script>";
            }
            $stmt->close();
        }
        ?>

        <div class="p-6">
            <button onclick="openAddEditPopup()" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-cyan-600 rounded-md mb-6 transition-all">Create Meeting</button>
            <button onclick="deleteSelectedMeetings()" class="bg-red-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-red-700 rounded-md mb-6 ml-4 transition-all opacity-25 cursor-not-allowed" disabled>Delete Selected</button>

            <table id="meetings-table" class="min-w-full bg-white shadow-lg rounded-md">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="border px-4 py-2 rounded-tl-md">
                            <input type="checkbox" id="select-all" class="cursor-pointer w-5 h-5">
                        </th>
                        <th class="border px-4 py-2">No</th>
                        <th class="border px-4 py-2">Meeting Title</th>
                        <th class="border px-4 py-2">Date</th>
                        <th class="border px-4 py-2">Time</th>
                        <th class="border px-4 py-2">Faculty</th>
                        <th class="border px-4 py-2">Semester</th>
                        <th class="border px-4 py-2 rounded-tr-md">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT zli.id, zli.zoom_link_title, zli.zoom_date, zli.zoom_link_time, zli.zoom_link, 
                            si.id as sem_info_id, si.sem, si.edu_type,
                            CONCAT(fi.first_name, ' ', fi.last_name) AS faculty_name
                            FROM zoom_link_info zli
                            JOIN faculty_info fi ON zli.faculty_info_id = fi.id
                            JOIN sem_info si ON zli.sem_info_id = si.id
                            ORDER BY zli.zoom_date";
                    $result = mysqli_query($conn, $query);

                    if (mysqli_num_rows($result) > 0) {
                        $counter = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $date_formatted = date("Y-m-d", strtotime($row['zoom_date']));
                            $time_formatted = date("H:i", strtotime($row['zoom_link_time']));
                            echo "<tr>
                                <td class='border px-4 py-2 text-center'>
                                    <input type='checkbox' name='selected_ids[]' value='{$row['id']}' class='select-checkbox h-4 w-4 cursor-pointer'>
                                </td>
                                <td class='border px-4 py-2 text-center'>{$counter}</td>
                                <td class='border px-4 py-2'>" . htmlspecialchars($row['zoom_link_title']) . "</td>
                                <td class='border px-4 py-2 text-center'>" . date("d/m/Y", strtotime($row['zoom_date'])) . "</td>
                                <td class='border px-4 py-2 text-center'>" . date("h:i A", strtotime($row['zoom_link_time'])) . "</td>
                                <td class='border px-4 py-2'>" . htmlspecialchars($row['faculty_name']) . "</td>
                                <td class='border px-4 py-2'>" . htmlspecialchars($row['sem'] . ' - ' . $row['edu_type']) . "</td>
                                <td class='border px-4 py-2 text-center'>
                                    <button onclick=\"openAddEditPopup({$row['id']}, '" . addslashes($row['zoom_link_title']) . "', '{$date_formatted}', '{$time_formatted}', '" . addslashes($row['zoom_link']) . "', '{$row['sem_info_id']}')\" class='text-blue-500 mr-2'>Edit</button>
                                </td>
                            </tr>";
                            $counter++;
                        }
                    } else {
                        echo "<tr><td colspan='8' class='border px-4 py-2 text-center'>No meetings found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center">
            <div class="bg-white rounded-lg p-6 w-96">
                <h2 id="popup-title" class="text-xl font-bold mb-4">Create/Edit Meeting</h2>
                <form id="popup-form" method="POST">
                    <input type="hidden" name="meeting_id" id="meeting_id">
                    <div class="mb-4">
                        <label for="meeting_title" class="block text-sm font-medium mb-1">Meeting Title</label>
                        <input type="text" id="meeting_title" name="meeting_title" class="border rounded p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="meeting_link" class="block text-sm font-medium mb-1">Meeting Link</label>
                        <input type="url" id="meeting_link" name="meeting_link" class="border rounded p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="meeting_date" class="block text-sm font-medium mb-1">Meeting Date</label>
                        <input type="date" id="meeting_date" name="meeting_date" class="border rounded p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="meeting_time" class="block text-sm font-medium mb-1">Meeting Time</label>
                        <input type="time" id="meeting_time" name="meeting_time" class="border rounded p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="semId" class="block text-sm font-medium mb-1">Select Semester</label>
                        <select id="semId" name="semId" class="border rounded p-2 w-full" required>
                            <?php
                            $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY sem, edu_type";
                            $sem_result = mysqli_query($conn, $sem_query);

                            if (mysqli_num_rows($sem_result) > 0) {
                                while ($sem_row = mysqli_fetch_assoc($sem_result)) {
                                    echo "<option value='" . $sem_row['id'] . "'>" . htmlspecialchars($sem_row['sem'] . ' - ' . $sem_row['edu_type']) . "</option>";
                                }
                            } else {
                                echo "<option value='' disabled>No semesters available</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 text-white p-2 rounded-full">Cancel</button>
                        <button type="submit" id="popup-submit" class="pl-6 pr-6 bg-cyan-500 text-white p-2 rounded-full">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            $(document).ready(function () {
                $('#meetings-table').DataTable({
                    paging: false,
                    info: false,
                    searching: true,
                    columnDefs: [{ orderable: false, targets: 0 }],
                    order: [[3, 'desc']] // Sort by date descending
                });

                $('#select-all').on('click', function () {
                    $('.select-checkbox').prop('checked', this.checked);
                    toggleDeleteButton();
                });

                $('.select-checkbox').on('change', toggleDeleteButton);

                function toggleDeleteButton() {
                    const selected = $('.select-checkbox:checked').length > 0;
                    const deleteButton = document.querySelector("button[onclick='deleteSelectedMeetings()']");
                    if (selected) {
                        deleteButton.disabled = false;
                        deleteButton.classList.remove('opacity-25', 'cursor-not-allowed');
                    } else {
                        deleteButton.disabled = true;
                        deleteButton.classList.add('opacity-25', 'cursor-not-allowed');
                    }
                }

                toggleDeleteButton();

                // Handle form submission with loading popup
                $('#popup-form').on('submit', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait while the meeting is being saved.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: './add_zoom_meeting.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function (response) {
                            Swal.close();
                            if (response.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    window.location.href = './add_zoom_meeting.php';
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            Swal.close();
                            Swal.fire({
                                title: 'Error!',
                                text: 'An error occurred while saving the meeting: ' . error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                });
            });

            function openAddEditPopup(id = null, title = '', date = '', time = '', link = '', semId = '') {
                document.getElementById('popup-title').innerText = id ? 'Edit Meeting' : 'Create Meeting';
                document.getElementById('meeting_id').value = id || '';
                document.getElementById('meeting_title').value = title || '';
                document.getElementById('meeting_date').value = date || '';
                document.getElementById('meeting_time').value = time || '';
                document.getElementById('meeting_link').value = link || '';
                document.getElementById('semId').value = semId || '';
                document.getElementById('popup-modal').classList.remove('hidden');
            }

            function closePopup() {
                document.getElementById('popup-modal').classList.add('hidden');
            }

            function deleteSelectedMeetings() {
                const selectedIds = [];
                $('.select-checkbox:checked').each(function () {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'No meetings selected.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete them!',
                    cancelButtonText: 'No, keep them'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: './add_zoom_meeting.php',
                            method: 'POST',
                            data: { delete_ids: selectedIds },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Deleted!',
                                        text: response.message,
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: response.message,
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Error deleting meetings: ' + error,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        });
                    }
                });
            }
        </script>
    </div>
</body>
</html>
<?php
// Flush output buffer at the end
ob_end_flush();
?>