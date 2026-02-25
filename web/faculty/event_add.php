<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');

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
$is_edit = false;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit = true;
    $event_id = intval($_GET['id']);
    $query = "SELECT id, title, datetime, details FROM events_info WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $datetime = isset($_POST['datetime']) ? $_POST['datetime'] : '';
    $details = isset($_POST['details']) ? $_POST['details'] : '';

    if (empty($title) || strlen($title) > 200 || empty($datetime) || empty($details)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required and title must be 200 characters or less.']);
        exit;
    }

    if ($action === 'add_event') {
        $query = "INSERT INTO events_info (title, datetime, details) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sss', $title, $datetime, $details);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Event added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add event']);
        }
        exit;
    }

    if ($action === 'edit_event') {
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ($event_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid event ID']);
            exit;
        }
        $query = "UPDATE events_info SET title = ?, datetime = ?, details = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $datetime, $details, $event_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Event updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update event']);
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
    <title><?php echo $is_edit ? 'Edit Event' : 'Add Event'; ?></title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        #editor {
            height: 300px;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = $is_edit ? 'Edit Event' : 'Add Event';
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6 flex flex-col items-center">
            <div class="w-full bg-white p-6 rounded-xl shadow-md">
                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-gray-700 font-bold mb-2">Title</label>
                        <input type="text" id="title" value="<?php echo $is_edit ? htmlspecialchars($event['title']) : ''; ?>" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" maxlength="200">
                    </div>
                    <div>
                        <label for="datetime" class="block text-gray-700 font-bold mb-2">Date & Time</label>
                        <input type="datetime-local" id="datetime" value="<?php echo $is_edit ? str_replace(' ', 'T', $event['datetime']) : ''; ?>" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="editor" class="block text-gray-700 font-bold mb-2">Details</label>
                        <div id="editor"><?php echo $is_edit ? $event['details'] : ''; ?></div>
                    </div>
                    <div class="flex justify-end">
                        <button id="submit-btn" class="bg-green-500 shadow-md hover:shadow-xl px-6 text-white p-2 rounded-full hover:bg-green-600 transition-all"><?php echo $is_edit ? 'Update' : 'Add'; ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        ['link'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['clean']
                    ]
                }
            });

            $('#submit-btn').click(function() {
                const title = $('#title').val().trim();
                const datetime = $('#datetime').val();
                const details = quill.root.innerHTML.trim();

                if (!title || title.length > 200 || !datetime || details === '<p><br></p>') {
                    Swal.fire('Error', 'All fields are required and title must be 200 characters or less.', 'error');
                    return;
                }

                const action = '<?php echo $is_edit ? 'edit_event' : 'add_event'; ?>';
                const data = {
                    action: action,
                    title: title,
                    datetime: datetime,
                    details: details
                };
                <?php if ($is_edit): ?>
                data.event_id = <?php echo $event['id']; ?>;
                <?php endif; ?>

                $.ajax({
                    url: 'event_add.php',
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Success', response.message, 'success').then(() => {
                                window.location.href = 'event_list.php';
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to save event.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('save_event AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to save event. Check the console for details.', 'error');
                    }
                });
            });
        });
    </script>
</body>

</html>