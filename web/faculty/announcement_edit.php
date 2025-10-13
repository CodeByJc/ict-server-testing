<?php
ob_start();
session_start();
include('../../api/db/db_connection.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'];
$user = $_SESSION['user'];

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<script>alert('Invalid request'); window.location.href='announcement_list.php';</script>";
    exit();
}

// ✅ Fetch existing announcement
$query = $conn->prepare("SELECT * FROM announcements WHERE Announcement_id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();
$announcement = $result->fetch_assoc();

if (!$announcement) {
    echo "<script>alert('Announcement not found'); window.location.href='announcement_list.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Announcement</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">

<?php include('./sidebar.php'); ?>

<div class="main-content pl-64 flex-1 overflow-y-auto">
<?php
$page_title = "Edit Announcement";
include('./navbar.php');
?>

<div class="mt-6 px-6">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-2xl font-semibold mb-4">Edit Announcement</h2>

        <form id="editForm" method="POST" class="space-y-4">
            <input type="hidden" name="Announcement_id" value="<?php echo htmlspecialchars($announcement['Announcement_id'] ?? ''); ?>">

            <!-- Title -->
            <div>
                <label class="block mb-1 font-medium">Announcement Title</label>
                <input type="text" name="Announcement_title" required
                    value="<?php echo htmlspecialchars($announcement['Announcement_title'] ?? ''); ?>"
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500">
            </div>

            <!-- Type -->
            <div>
                <label class="block mb-1 font-medium">Announcement Type</label>
                <select name="Announcement_type_id" required
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500">
                    <option value="">-- Select Type --</option>
                    <?php
                    $typeQuery = $conn->query("SELECT announcement_type_id, announcement_type FROM announcement_type");
                    while ($type = $typeQuery->fetch_assoc()) {
                        $selected = ($announcement['Announcement_type_id'] == $type['announcement_type_id']) ? 'selected' : '';
                        echo "<option value='{$type['announcement_type_id']}' $selected>{$type['announcement_type']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- ✅ Batch Section (Fixed for batch_info table) -->
            <div>
                <label class="block mb-1 font-medium">Batch</label>
                <select name="batch_id" required
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500">
                    <option value="">-- Select Batch --</option>
                    <?php
                    $batchQuery = $conn->query("SELECT id, batch_start_year, batch_end_year FROM batch_info");
                    while ($batch = $batchQuery->fetch_assoc()) {
                        $label = $batch['batch_start_year'] . " - " . $batch['batch_end_year'];
                        $selected = ($announcement['announcement_type_id'] == $batch['id']) ? 'selected' : '';
                        echo "<option value='{$batch['id']}' $selected>{$label}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Description -->
            <div>
                <label class="block mb-1 font-medium">Announcement Description</label>
                <textarea name="announcement_description" rows="4" required
                    class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500"><?php echo htmlspecialchars($announcement['announcement_description'] ?? ''); ?></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" 
                    class="bg-cyan-500 text-white px-4 py-2 rounded-lg hover:bg-cyan-600 transition">
                    Update Announcement
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
$('#editForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'announcement_update.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(resp) {
            if (resp.status === 'success') {
                Swal.fire('Updated!', resp.message, 'success').then(() => {
                    window.location.href = 'announcement_list.php';
                });
            } else {
                Swal.fire('Error', resp.message || 'Something went wrong', 'error');
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
            Swal.fire('Error', 'Request failed. Check console for details.', 'error');
        }
    });
});
</script>

</body>
</html>

<?php ob_end_flush(); ?>
