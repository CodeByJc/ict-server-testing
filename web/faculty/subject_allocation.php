<?php
include('../../api/db/db_connection.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    if ($action === 'fetch') {
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;

        if ($subject_id <= 0) {
            echo json_encode(['error' => 'Invalid subject ID']);
            exit;
        }

        $query = "SELECT sa.id AS allocation_id, fi.id, fi.first_name, fi.last_name, ul.email 
                  FROM subject_allocation sa 
                  JOIN faculty_info fi ON sa.faculty_info_id = fi.id 
                  JOIN user_login ul ON fi.user_login_id = ul.username 
                  WHERE sa.subject_info_id = ? 
                  ORDER BY fi.first_name, fi.last_name";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $subject_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $faculties = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $faculties[] = $row;
        }

        echo json_encode($faculties);

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    if ($action === 'allocate') {
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        $faculty_id = isset($_POST['faculty_info_id']) ? intval($_POST['faculty_info_id']) : 0;

        if ($subject_id <= 0 || $faculty_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid subject or faculty ID']);
            exit;
        }

        // Check if allocation already exists
        $check_query = "SELECT id FROM subject_allocation WHERE subject_info_id = ? AND faculty_info_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'ii', $subject_id, $faculty_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Faculty is already allocated to this subject']);
            mysqli_stmt_close($check_stmt);
            exit;
        }
        mysqli_stmt_close($check_stmt);

        // Insert new allocation
        $insert_query = "INSERT INTO subject_allocation (subject_info_id, faculty_info_id) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, 'ii', $subject_id, $faculty_id);

        if (mysqli_stmt_execute($insert_stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to allocate faculty']);
        }

        mysqli_stmt_close($insert_stmt);
        mysqli_close($conn);
        exit;
    }

    if ($action === 'delete') {
        $allocation_id = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($allocation_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid allocation ID']);
            exit;
        }

        $query = "DELETE FROM subject_allocation WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $allocation_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete allocation']);
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
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
    <title>Subject Allocation</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #faculty-table {
            border-collapse: collapse;
        }
        #faculty-table th, #faculty-table td {
            text-align: center;
            border: 1px solid #d1d5db; /* gray-300 */
        }
        #faculty-table th {
            background-color: #374151; /* gray-700 */
            color: #ffffff; /* white */
        }
        #faculty-table tbody tr:hover {
            background-color: #f9fafb; /* gray-50 */
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Subject Allocation";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                <div class="w-full md:w-1/2 mb-4">
                    <label for="subject" class="block text-gray-700 font-bold mb-2">Select Subject</label>
                    <select id="subject" name="subject" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="" disabled selected>Select Subject</option>
                        <?php
                        $subject_query = "SELECT si.id, si.subject_name, smi.sem, smi.edu_type 
                                         FROM subject_info si 
                                         JOIN sem_info smi ON si.sem_info_id = smi.id 
                                         ORDER BY smi.edu_type, smi.sem, si.subject_name";
                        $subject_result = mysqli_query($conn, $subject_query);
                        while ($row = mysqli_fetch_assoc($subject_result)) {
                            echo "<option value='{$row['id']}'>{$row['subject_name']} (SEM {$row['sem']} - " . strtoupper($row['edu_type']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="p-6 bg-white rounded-xl shadow-md">
                <button id="allocate-btn" onclick="openAllocatePopup()" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-cyan-600 transition-all mb-6" disabled>Allocate Faculty</button>
                <table id="faculty-table" class="min-w-full bg-white shadow-lg rounded-md border border-gray-300">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2 rounded-tl-md">No</th>
                            <th class="border px-4 py-2">Faculty Name</th>
                            <th class="border px-4 py-2">Email</th>
                            <th class="border px-4 py-2 rounded-tr-md">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div id="allocate-popup" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center">
            <div class="bg-white rounded-lg p-6 w-96">
                <h2 id="popup-title" class="text-xl font-bold mb-4">Allocate Faculty</h2>
                <form id="allocate-form">
                    <input type="hidden" name="subject_info_id" id="subject_info_id">
                    <div class="mb-4">
                        <label for="faculty" class="block text-sm font-medium mb-1">Select Faculty</label>
                        <select id="faculty" name="faculty_info_id" class="border-2 rounded p-2 w-full" required>
                            <option value="" disabled selected>Select Faculty</option>
                            <?php
                            $faculty_query = "SELECT id, first_name, last_name FROM faculty_info ORDER BY first_name, last_name";
                            $faculty_result = mysqli_query($conn, $faculty_query);
                            while ($row = mysqli_fetch_assoc($faculty_result)) {
                                echo "<option value='{$row['id']}'>{$row['first_name']} {$row['last_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 hover:bg-gray-600 text-white p-2 rounded-full">Cancel</button>
                        <button type="submit" class="pl-6 pr-6 bg-cyan-500 hover:bg-cyan-600 text-white p-2 rounded-full">Allocate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            let selectedSubjectId = '';
            let selectedSubjectName = '';
            const table = $('#faculty-table').DataTable({
                paging: false,
                info: false,
                searching: false,
                ordering: false,
                language: {
                    emptyTable: 'Please select a subject to view allocated faculties'
                },
                columns: [
                    { data: 'no' },
                    { data: 'faculty_name' },
                    { data: 'email' },
                    { data: 'actions' }
                ]
            });

            // Enable/Disable Allocate Button
            $('#subject').change(function () {
                selectedSubjectId = $(this).val();
                selectedSubjectName = $(this).find('option:selected').text();
                console.log('Selected subject_id:', selectedSubjectId, 'Name:', selectedSubjectName);
                $('#allocate-btn').prop('disabled', !selectedSubjectId);

                if (selectedSubjectId) {
                    $.ajax({
                        url: 'subject_allocation.php',
                        method: 'POST',
                        data: { action: 'fetch', subject_info_id: selectedSubjectId },
                        dataType: 'json',
                        success: function (data) {
                            console.log('fetch response:', data);
                            if (!Array.isArray(data)) {
                                console.error('Expected an array, got:', data);
                                Swal.fire('Error', data.error || 'Invalid response format from server.', 'error');
                                table.clear().draw();
                                return;
                            }

                            table.clear();
                            if (data.length === 0) {
                                table.draw();
                                return;
                            }

                            const rows = data.map((faculty, index) => ({
                                no: index + 1,
                                faculty_name: `${faculty.first_name || 'N/A'} ${faculty.last_name || ''}`,
                                email: faculty.email || 'N/A',
                                actions: `<button type="button" onclick="deleteAllocation(${faculty.allocation_id})" class="text-red-500">Delete</button>`
                            }));
                            table.rows.add(rows).draw();
                        },
                        error: function (xhr, status, error) {
                            console.error('fetch AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load faculties. Check the console for details.', 'error');
                            table.clear().draw();
                        }
                    });
                } else {
                    table.clear().draw();
                }
            });

            // Handle Allocation Form Submission
            $('#allocate-form').submit(function (e) {
                e.preventDefault();
                const formData = $(this).serialize() + '&action=allocate';
                $.ajax({
                    url: 'subject_allocation.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        console.log('allocate response:', response);
                        if (response.status === 'success') {
                            Swal.fire({
                                title: 'Allocated!',
                                text: 'Faculty has been allocated successfully.',
                                icon: 'success'
                            }).then(() => {
                                closePopup();
                                $('#subject').trigger('change');
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to allocate faculty.', 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('allocate AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to allocate faculty. Check the console for details.', 'error');
                    }
                });
            });
        });

        function openAllocatePopup() {
            const subjectName = $('#subject option:selected').text();
            $('#popup-title').text(`Allocate Faculty for ${subjectName}`);
            $('#subject_info_id').val($('#subject').val());
            $('#faculty').val('');
            $('#allocate-popup').removeClass('hidden');
        }

        function closePopup() {
            $('#allocate-popup').addClass('hidden');
            $('#allocate-form')[0].reset();
            $('#subject_info_id').val('');
        }

        function deleteAllocation(allocation_id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This faculty allocation will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'subject_allocation.php',
                        method: 'POST',
                        data: { action: 'delete', allocation_id: allocation_id },
                        dataType: 'json',
                        success: function (response) {
                            console.log('delete response:', response);
                            if (response.status === 'success') {
                                Swal.fire('Deleted!', 'Faculty allocation has been deleted.', 'success').then(() => {
                                    $('#subject').trigger('change');
                                });
                            } else {
                                Swal.fire('Error', response.message || 'Failed to delete allocation.', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('delete AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to delete allocation. Check the console for details.', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>

</html>