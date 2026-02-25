<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

// Check database connection
if (mysqli_connect_errno()) {
    error_log("Database connection failed: " . mysqli_connect_error());
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_events') {
        header('Content-Type: application/json');
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
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

// Handle AJAX request for fetching events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_events') {
    header('Content-Type: application/json');
    try {
        $query = "SELECT id, title, datetime, created_at FROM events_info ORDER BY datetime DESC";
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }
        $events = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Format dates
            $row['datetime'] = date('d-m-Y - h:i A', strtotime($row['datetime']));
            $row['created_at'] = date('d-m-Y - h:i A', strtotime($row['created_at']));
            $events[] = $row;
        }
        ob_end_clean(); // Clear buffer before JSON output
        echo json_encode(['status' => 'success', 'events' => $events]);
    } catch (Exception $e) {
        ob_end_clean();
        error_log("fetch_events error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch events']);
    }
    mysqli_close($conn);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event List</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #event-table {
            border-collapse: collapse;
        }
        #event-table th,
        #event-table td {
            text-align: center;
            border: 1px solid #d1d5db;
        }
        #event-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #event-table tbody tr:hover {
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
        $page_title = "Event List";
        include('./navbar.php');
        ?>
        <div class="mt-6">
            <div class="flex items-center ml-5 mr-5">
                <a href="event_add.php" class="bg-cyan-500 shadow-md mr-7 hover:shadow-xl px-6 text-white p-2 rounded-lg hover:bg-cyan-600 transition-all">Add Event</a>
                <input type="text" id="search" class="shadow-lg pl-4 p-2 rounded-md w-1/2" placeholder="Search by title or date..." onkeyup="searchEvents()">
            </div>
        </div>
        <div class="p-5">
            <table id="event-table" class="min-w-full bg-white shadow-md rounded-md table-fixed">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="border px-4 py-2 rounded-tl-md w-4/12">Title</th>
                        <th class="border px-4 py-2 w-4/12">Date & Time</th>
                        <th class="border px-4 py-2 rounded-tr-md w-4/12">Created At</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const table = $('#event-table').DataTable({
                ajax: {
                    url: 'event_list.php',
                    type: 'POST',
                    data: { action: 'fetch_events' },
                    dataSrc: function(json) {
                        if (json.status === 'success') {
                            return json.events;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: json.message || 'Failed to load events.',
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
                            text: 'Failed to load events. Check the console for details.',
                            confirmButtonColor: '#06b6d4'
                        });
                    }
                },
                columns: [
                    { data: 'title' },
                    { data: 'datetime' },
                    { data: 'created_at' }
                ],
                paging: false,
                searching: false,
                ordering: false,
                info: false
            });

            $('#event-table tbody').on('click', 'tr', function() {
                const data = table.row(this).data();
                window.location.href = 'event_details.php?id=' + data.id;
            });
        });

        // Real-time search function for events by title and date
        function searchEvents() {
            const searchInput = document.getElementById('search').value.toLowerCase();
            const rows = document.querySelectorAll('#event-table tbody tr');

            rows.forEach(row => {
                const title = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const date = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (title.includes(searchInput) || date.includes(searchInput)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>
<?php ob_end_flush(); // Flush output buffer ?>