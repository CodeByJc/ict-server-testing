<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

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

$event = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $event_id = intval($_GET['id']);
    $query = "SELECT id, title, datetime, details, created_at FROM events_info WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$event) {
    header("Location: event_list.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    header('Content-Type: application/json');
    ob_end_clean(); // Clear buffer
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if ($event_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid event ID']);
        exit;
    }
    $query = "DELETE FROM events_info WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    if ($success) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete event']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>

<body class="bg-gray-100 text-gray-800">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Event Details";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <!-- Back Button -->
            <a href="event_list.php" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-solid fa-angle-left"></i> Back
            </a>
            <!-- Edit Button -->
            <a href="event_add.php?id=<?php echo $event['id']; ?>" class="ml-3 text-white bg-cyan-600 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-regular fa-pen-to-square"></i> Edit
            </a>
            <!-- Delete Form -->
            <form id="deleteForm" method="POST" action="" class="inline">
                <input type="hidden" name="drive_id" value="<?php echo $event['id']; ?>">
                <button type="button" id="deleteButton" class="ml-3 text-white bg-red-500 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                    <i class="fa-regular fa-trash-can"></i> Delete
                </button>
            </form>
            <!-- Event Information -->
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="rounded-full w-full h-1 mt-2 bg-slate-100"></div>
                <!-- Grid Layout -->
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <!-- Date & Time -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Date & Time</span>
                        </h2>
                        <div class="pl-5 text-gray-700">
                            <?php echo date('d/m/Y - g:i A', strtotime($event['datetime'])); ?>
                        </div>
                    </div>
                    <!-- Created At -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Created At</span>
                        </h2>
                        <div class="pl-5 text-gray-700">
                            <?php echo date('d/m/Y - g:i A', strtotime($event['created_at'])); ?>
                        </div>
                    </div>
                    <!-- Details -->
                    <div class="col-span-2">
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Details</span>
                        </h2>
                        <div class="pl-5 text-gray-700 prose">
                            <?php echo $event['details']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#deleteButton').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = $('#deleteForm');
                        var formData = new FormData(form[0]);
                        formData.append('action', 'delete_event');
                        formData.append('event_id', <?php echo $event['id']; ?>);
                        $.ajax({
                            url: form.attr('action'),
                            method: form.attr('method'),
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Deleted!', 'The event has been deleted.', 'success').then(() => {
                                        window.location.href = 'event_list.php';
                                    });
                                } else {
                                    Swal.fire('Error!', data.message || 'Failed to delete the event.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Delete AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to delete the event. Check the console for details.', 'error');
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