<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../../api/db/db_connection.php');

if (mysqli_connect_errno()) {
    error_log("Database connection failed: " . mysqli_connect_error());
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_announcements') {
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

// ✅ Handle AJAX: fetch_announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_announcements') {
    header('Content-Type: application/json');
    try {
        $query = "SELECT a.Announcement_id, a.faculty_id, a.Announcement_title, a.Announcement_date, t.Announcement_type
                  FROM announcements_info a
                  LEFT JOIN announcement_type_list t ON a.Announcement_type_id = t.Announcement_type_id
                  ORDER BY a.Announcement_date DESC";
        $res = mysqli_query($conn, $query);
        if (!$res) throw new Exception(mysqli_error($conn));
        $ann = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $row['Announcement_date'] = date('d-m-Y', strtotime($row['Announcement_date']));
            $ann[] = $row;
        }
        ob_end_clean();
        echo json_encode(['status' => 'success', 'announcements' => $ann]);
    } catch (Exception $e) {
        ob_end_clean();
        error_log("fetch_announcements error: ".$e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch announcements']);
    }
    mysqli_close($conn);
    exit;
}

// ✅ Handle AJAX: delete_announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['status'=>'error','message'=>'Invalid announcement id']);
        exit;
    }
    try {
        $stmt = mysqli_prepare($conn, "DELETE FROM announcements_info WHERE Announcement_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        if ($ok) {
            echo json_encode(['status'=>'success','message'=>'Announcement deleted']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Failed to delete']);
        }
    } catch (Exception $e) {
        error_log("delete_announcement error: ".$e->getMessage());
        echo json_encode(['status'=>'error','message'=>'Failed to delete']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Announcements</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">

<?php include('./sidebar.php'); ?>

<div class="main-content pl-64 flex-1 overflow-y-auto">
<?php
$page_title = "Announcements";
include('./navbar.php');
?>
<div class="mt-6">
    <div class="flex items-center ml-5 mr-5">
        <a href="announcement_add.php" class="bg-cyan-500 shadow text-white px-4 py-2 rounded-lg hover:bg-cyan-600 transition-all">Add Announcement</a>
        <div class="flex-1"></div>
    </div>

    <div class="p-5">
        <div class="bg-white shadow rounded-lg p-4">
            <table id="announcement-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Faculty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const table = $('#announcement-table').DataTable({
        ajax: {
            url: 'announcement_list.php',
            type: 'POST',
            data: { action: 'fetch_announcements' },
            dataSrc: function(json) {
                if (json.status === 'success') {
                    return json.announcements;
                } else {
                    Swal.fire('Error', json.message || 'Failed to load announcements', 'error');
                    return [];
                }
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown, 'Response:', xhr.responseText);
                Swal.fire('Error', 'Failed to load announcements. See console.', 'error');
            }
        },
        columns: [
            { data: 'Announcement_title' },
            { data: 'Announcement_type' },
            { data: 'Announcement_date' },
            { data: 'faculty_id' },
            { 
                data: 'Announcement_id',
                render: function(data, type, row) {
                    return `
                        <a href="announcement_edit.php?id=${data}" 
                           class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded">
                           Edit
                        </a>
                        <button class="px-3 py-1 rounded bg-red-500 text-white ml-2" 
                                onclick="deleteAnnouncement(${data})">
                                Delete
                        </button>`;
                }
            }
        ]
    });
});

function deleteAnnouncement(id) {
    Swal.fire({
        title: 'Delete this announcement?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#d33'
    }).then((res) => {
        if (res.isConfirmed) {
            $.post('announcement_list.php', { action: 'delete_announcement', id: id }, function(resp) {
                if (resp.status === 'success') {
                    Swal.fire('Deleted', resp.message, 'success');
                    $('#announcement-table').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', resp.message || 'Delete failed', 'error');
                }
            }, 'json').fail(function(xhr) {
                console.error('delete error', xhr.responseText);
                Swal.fire('Error', 'Delete failed. See console.', 'error');
            });
        }
    });
}
</script>
</div>
</body>
</html>
<?php ob_end_flush(); ?>
