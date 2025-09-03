<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../api/db/db_connection.php';

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

// Fetch all batches
$stmt = $conn->prepare("SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year ASC");
$stmt->execute();
$result = $stmt->get_result();
$batches = $result->fetch_all(MYSQLI_ASSOC);

// Handle AJAX add batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_batch') {
    header('Content-Type: application/json');
    ob_end_clean();

    $start_year = isset($_POST['batch_start_year']) ? trim($_POST['batch_start_year']) : null;
    $end_year = isset($_POST['batch_end_year']) ? trim($_POST['batch_end_year']) : null;
    $current_year = (int)date('Y');
    $min_year = 2000;
    $max_year = $current_year + 20;

    // Validate inputs
    if (!$start_year || !$end_year) {
        echo json_encode(['status' => 'error', 'message' => 'Both start year and end year are required.']);
        exit;
    }
    if (!preg_match('/^\d{4}$/', $start_year) || !preg_match('/^\d{4}$/', $end_year)) {
        echo json_encode(['status' => 'error', 'message' => 'Years must be valid 4-digit numbers.']);
        exit;
    }
    $start_year = (int)$start_year;
    $end_year = (int)$end_year;
    if ($start_year < $min_year || $start_year > $max_year || $end_year < $min_year || $end_year > $max_year) {
        echo json_encode(['status' => 'error', 'message' => "Years must be between $min_year and $max_year."]);
        exit;
    }
    if ($end_year <= $start_year) {
        echo json_encode(['status' => 'error', 'message' => 'End year must be greater than start year.']);
        exit;
    }

    // Check for duplicate
    $stmt = $conn->prepare("SELECT id FROM batch_info WHERE batch_start_year = ? AND batch_end_year = ?");
    $stmt->bind_param("ss", $start_year, $end_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This batch already exists.']);
        exit;
    }

    // Insert batch
    try {
        $stmt = $conn->prepare("INSERT INTO batch_info (batch_start_year, batch_end_year) VALUES (?, ?)");
        $stmt->bind_param("ss", $start_year, $end_year);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Batch added successfully.']);
    } catch (Exception $e) {
        error_log("Batch insert error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to add batch.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #batches-table {
            border-collapse: collapse;
            width: 100%;
        }
        #batches-table th,
        #batches-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            min-width: 150px;
            padding: 8px;
        }
        #batches-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #batches-table tbody tr:hover {
            background-color: #e5e7eb;
            font-weight: bold;
            cursor: pointer;
        }
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .swal2-popup select {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            margin-top: 4px;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Manage Batches";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h1 class="text-2xl font-bold">Batch List</h1>
                    <button id="add-batch-btn" class="bg-green-500 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                        <i class="fa-solid fa-plus"></i> Add Batch
                    </button>
                </div>
                <div class="table-container">
                    <table id="batches-table" class="min-w-full bg-white shadow-md rounded-md">
                        <thead>
                            <tr class="bg-gray-700 text-white">
                                <th class="border px-4 py-2 rounded-tl-md">Batch ID</th>
                                <th class="border px-4 py-2">Start Year</th>
                                <th class="border px-4 py-2 rounded-tr-md">End Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($batch['id']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($batch['batch_start_year']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($batch['batch_end_year']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with horizontal scrolling
            $('#batches-table').DataTable({
                scrollX: true,
                paging: false,
                searching: false,
                ordering: true,
                info: false,
                columnDefs: [
                    { width: '150px', targets: '_all' }
                ]
            });

            // Generate year options
            function generateYearOptions(minYear, maxYear, selectedYear = '') {
                let options = '<option value="" disabled selected>Select year</option>';
                for (let year = minYear; year <= maxYear; year++) {
                    const selected = year == selectedYear ? ' selected' : '';
                    options += `<option value="${year}"${selected}>${year}</option>`;
                }
                return options;
            }

            // Handle Add Batch button
            $('#add-batch-btn').click(function() {
                const minYear = 2015;
                const maxYear = new Date().getFullYear() + 10;
                
                Swal.fire({
                    title: 'Add New Batch',
                    html: `
                        <div class="space-y-4">
                            <div>
                                <label for="batch_start_year" class="block text-sm font-medium text-gray-700">Start Year</label>
                                <select id="batch_start_year" class="mt-1 block w-full" required>
                                    ${generateYearOptions(minYear, maxYear)}
                                </select>
                            </div>
                            <div>
                                <label for="batch_end_year" class="block text-sm font-medium text-gray-700">End Year</label>
                                <select id="batch_end_year" class="mt-1 block w-full" required>
                                    ${generateYearOptions(minYear, maxYear)}
                                </select>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Add',
                    didOpen: () => {
                        // Dynamically update end_year options based on start_year
                        const startSelect = document.getElementById('batch_start_year');
                        const endSelect = document.getElementById('batch_end_year');
                        startSelect.addEventListener('change', () => {
                            const startYear = parseInt(startSelect.value);
                            if (startYear) {
                                // Clear and regenerate end_year options
                                endSelect.innerHTML = generateYearOptions(startYear + 1, maxYear);
                            } else {
                                // Reset to default
                                endSelect.innerHTML = generateYearOptions(minYear, maxYear);
                            }
                        });
                    },
                    preConfirm: () => {
                        const startYear = document.getElementById('batch_start_year').value;
                        const endYear = document.getElementById('batch_end_year').value;
                        if (!startYear || !endYear) {
                            Swal.showValidationMessage('Both start year and end year are required.');
                            return false;
                        }
                        if (parseInt(endYear) <= parseInt(startYear)) {
                            Swal.showValidationMessage('End year must be greater than start year.');
                            return false;
                        }
                        return { startYear, endYear };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'You will not be allowed to delete this batch after adding. Are you sure to add?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#06b6d4',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, add it!'
                        }).then((confirmResult) => {
                            if (confirmResult.isConfirmed) {
                                $.ajax({
                                    url: './manage_batches.php',
                                    method: 'POST',
                                    data: {
                                        action: 'add_batch',
                                        batch_start_year: result.value.startYear,
                                        batch_end_year: result.value.endYear
                                    },
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
                                        console.error('Add batch AJAX error:', status, error, 'Response:', xhr.responseText);
                                        Swal.fire('Error!', 'Failed to add batch. Check the console for details.', 'error');
                                    }
                                });
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