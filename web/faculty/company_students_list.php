<?php
include('../../api/db/db_connection.php');

// Get campus_drive_info_id and company name from the URL
$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : null;
$company = isset($_GET['company']) ? mysqli_real_escape_string($conn, $_GET['company']) : null;

if (!$drive_id || !$company) {
    echo "<script>
            alert('Invalid drive ID or company name.');
            window.history.back();
          </script>";
    exit;
}

// Function to fetch total rounds
function getTotalRounds($conn, $drive_id)
{
    $query = "SELECT COUNT(*) AS total_rounds FROM company_rounds_info WHERE campus_placement_info_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $drive_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result)['total_rounds'];
}

// Function to fetch student data
function getStudents($conn, $drive_id)
{
    $query = "SELECT si.id AS student_id, CONCAT(si.first_name, ' ', si.last_name) AS student_name, si.enrollment_no, cde.datetime 
              FROM campus_drive_enroll cde
              JOIN student_info si ON cde.student_info_id = si.id
              WHERE cde.campus_drive_info_id = ? AND cde.status = 'yes'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $drive_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to fetch rounds for a student
function getStudentRounds($conn, $student_id, $drive_id)
{
    $rounds = [];
    $query = "CALL studentRounds(?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $student_id, $drive_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($round = mysqli_fetch_assoc($result)) {
        $rounds[] = $round;
    }
    mysqli_stmt_close($stmt);
    while (mysqli_next_result($conn)) { /* Clear results */
    }
    return $rounds;
}

// Function to fetch all rounds for dropdown
function getAllRounds($conn, $drive_id)
{
    $query = "SELECT id, round_index, round_name FROM company_rounds_info WHERE campus_placement_info_id = ? ORDER BY round_index";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $drive_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company); ?> Students List</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .progress-dot {
            transition: transform 0.2s;
        }

        .progress-dot:hover {
            transform: scale(1.2);
        }

        .modal-enter {
            opacity: 0;
            transform: scale(0.8);
        }

        .modal-enter-active {
            opacity: 1;
            transform: scale(1);
            transition: all 300ms ease;
        }

        .modal-exit {
            opacity: 1;
            transform: scale(1);
        }

        .modal-exit-active {
            opacity: 0;
            transform: scale(0.8);
            transition: all 300ms ease;
        }

        .fade-overlay {
            transition: opacity 300ms ease;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 flex h-screen overflow-hidden font-sans">
    <?php include('./sidebar.php'); ?>
    <div class="flex-1 ml-64 overflow-y-auto">
        <?php
        $page_title = htmlspecialchars("$company Registered Students");
        include('./navbar.php');

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['student_id'], $_POST['round_id'], $_POST['status'], $_POST['drive_id'])) {
                $student_id = intval($_POST['student_id']);
                $round_id = intval($_POST['round_id']);
                $status = mysqli_real_escape_string($conn, $_POST['status']);
                $drive_id = intval($_POST['drive_id']);

                if ($student_id && $round_id && $status && $drive_id) {
                    // Check if record exists
                    $check_query = "SELECT id FROM student_round_info WHERE student_info_id = ? AND campus_placement_info_id = ?";
                    $stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($stmt, 'ii', $student_id, $drive_id);
                    mysqli_stmt_execute($stmt);
                    $check_result = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($check_result) > 0) {
                        // Update record
                        $update_query = "UPDATE student_round_info SET status = ?, company_round_info_id = ? 
                                 WHERE student_info_id = ? AND campus_placement_info_id = ?";
                        $stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($stmt, 'siii', $status, $round_id, $student_id, $drive_id);
                        $success = mysqli_stmt_execute($stmt);
                    } else {
                        // Insert new record
                        $insert_query = "INSERT INTO student_round_info (student_info_id, campus_placement_info_id, company_round_info_id, status) 
                                 VALUES (?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($stmt, 'iiis', $student_id, $drive_id, $round_id, $status);
                        $success = mysqli_stmt_execute($stmt);
                    }

                    $message = $success ? ['success', 'Round status updated successfully!'] : ['error', 'Failed to update round status.'];
                    echo "<script>
                    Swal.fire({
                        icon: '{$message[0]}',
                        title: '{$message[0]}'.charAt(0).toUpperCase() + '{$message[0]}'.slice(1),
                        text: '{$message[1]}',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        " . ($success ? "window.location.href = 'company_students_list.php?drive_id=$drive_id&company=" . urlencode($company) . "';" : '') . "
                    });
                  </script>";
                } else {
                    echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'All fields are required!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                  </script>";
                }
            }
        }

        ?>

        <div class="p-6 max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <a href="campus_drive_company.php?drive_id=<?php echo $drive_id?>" class="flex items-center bg-gray-700 text-white px-4 py-2 rounded-full hover:bg-gray-800 transition">
                        <i class="fa-solid fa-angle-left mr-2"></i> Back
                    </a>
                    <div class="relative w-80">
                        <input type="text" id="search" class="w-full pl-10 pr-4 py-2 rounded-full border border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="Search by enrollment or name..." onkeyup="debounceSearch()">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <table id="student-table" class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Enrollment No.</th>
                            <th class="px-4 py-3 text-left">Student Name</th>
                            <th class="px-4 py-3 text-left">Registered On</th>
                            <th class="px-4 py-3 text-left">Round Status</th>
                            <th class="px-4 py-3 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_rounds = getTotalRounds($conn, $drive_id);
                        $result = getStudents($conn, $drive_id);

                        if (mysqli_num_rows($result) > 0) {
                            $counter = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                                $datetime = new DateTime($row['datetime']);
                                $formatted_datetime = $datetime->format('d/m/Y, h:i A');

                                echo "<tr class='border-b hover:bg-gray-50 transition'>";
                                echo "<td class='px-4 py-3'>$counter</td>";
                                echo "<td class='px-4 py-3'>{$row['enrollment_no']}</td>";
                                echo "<td class='px-4 py-3'>{$row['student_name']}</td>";
                                echo "<td class='px-4 py-3'>$formatted_datetime</td>";
                                echo "<td class='px-4 py-3'>";

                                $rounds = getStudentRounds($conn, $row['student_id'], $drive_id);
                                echo "<div class='flex gap-2 items-center'>";
                                foreach ($rounds as $round) {
                                    $color = $round['status'] === 'pass' ? 'bg-green-500' : ($round['status'] === 'reject' ? 'bg-red-500' : 'bg-gray-300');
                                    echo "<div class='progress-dot w-6 h-6 rounded-full $color cursor-pointer' title='Round {$round['round_index']}: {$round['round_name']}'></div>";
                                }
                                echo "</div></td>";
                                echo "<td class='px-4 py-3'>";
                                echo "<button type='button' onclick='openEditPopup({$row['student_id']}, \"{$row['student_name']}\", $drive_id)' class='text-blue-600 hover:text-blue-800 font-medium'>Edit</button>";
                                echo "</td></tr>";
                                $counter++;
                            }
                        } else {
                            echo "<tr><td colspan='6' class='px-4 py-3 text-center text-gray-500'>No students registered</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Popup Modal -->
            <div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-60 hidden flex items-center justify-center z-50 fade-overlay">
                <div class="bg-white rounded-xl p-6 w-full max-w-md transform modal-enter">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Update Round Status</h2>
                    <form id="popup-form" method="POST" onsubmit="return confirmSubmit()">
                        <input type="hidden" name="student_id" id="student_id">
                        <input type="hidden" name="drive_id" id="drive_id">

                        <div class="mb-4">
                            <label for="student_name" class="block text-sm font-medium text-gray-700">Student Name</label>
                            <input type="text" disabled id="student_name" name="student_name" class="mt-1 w-full p-2 border border-gray-300 rounded-lg bg-gray-100" required>
                        </div>

                        <div class="mb-4">
                            <label for="round_id" class="block text-sm font-medium text-gray-700">Select Round</label>
                            <select id="round_id" name="round_id" class="mt-1 w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" required>
                                <?php
                                $round_result = getAllRounds($conn, $drive_id);
                                if (mysqli_num_rows($round_result) > 0) {
                                    while ($round_row = mysqli_fetch_assoc($round_result)) {
                                        echo "<option value='{$round_row['id']}'>Round {$round_row['round_index']} - {$round_row['round_name']}</option>";
                                    }
                                } else {
                                    echo "<option value=''>No rounds available</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-6">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" class="mt-1 w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" required>
                                <option value="pass">Pass</option>
                                <option value="reject">Reject</option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closePopup()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                                <span id="submit-text">Save</span>
                                <svg id="loading-spinner" class="hidden w-5 h-5 ml-2 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8h8a8 8 0 01-16 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Debounce search input
        let searchTimeout;

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchTable, 300);
        }

        function searchTable() {
            const searchInput = document.getElementById('search').value.toLowerCase();
            const rows = document.querySelectorAll('#student-table tbody tr');
            rows.forEach(row => {
                const enrollmentNo = row.cells[1].textContent.toLowerCase();
                const studentName = row.cells[2].textContent.toLowerCase();
                row.style.display = (enrollmentNo.includes(searchInput) || studentName.includes(searchInput)) ? '' : 'none';
            });
        }

        function openEditPopup(studentId, studentName, driveId) {
            document.getElementById('student_id').value = studentId;
            document.getElementById('student_name').value = studentName;
            document.getElementById('drive_id').value = driveId;
            const modal = document.getElementById('popup-modal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.modal-enter').classList.add('modal-enter-active');
            }, 10);
        }

        function closePopup() {
            const modal = document.getElementById('popup-modal');
            modal.querySelector('.modal-enter').classList.remove('modal-enter-active');
            modal.querySelector('.modal-enter').classList.add('modal-exit-active');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.querySelector('.modal-enter').classList.remove('modal-exit-active');
            }, 300);
        }

        function confirmSubmit() {
            return new Promise(resolve => {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this round status?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, update it!'
                }).then(result => {
                    if (result.isConfirmed) {
                        const submitButton = document.querySelector('#popup-form button[type="submit"]');
                        const submitText = document.getElementById('submit-text');
                        const spinner = document.getElementById('loading-spinner');

                        submitButton.disabled = true;
                        submitText.classList.add('hidden');
                        spinner.classList.remove('hidden');

                        // Allow form submission
                        resolve(true);
                    } else {
                        resolve(false);
                    }
                });
            });
        }
    </script>
</body>

</html>