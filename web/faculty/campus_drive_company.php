<?php
include('../../api/db/db_connection.php');

// Function to fetch rounds for a company drive
function getCompanyRounds($drive_id) {
    global $conn;
    $query = "
        SELECT cri.id, cr.round_name, cri.mode, cri.round_index
        FROM company_rounds_info cri
        LEFT JOIN company_rounds cr ON cri.company_round_id = cr.round_id
        WHERE cri.campus_placement_info_id = $drive_id
        ORDER BY cri.round_index
    ";
    $result = mysqli_query($conn, $query);
    $rounds = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rounds[] = $row;
        }
    }
    return $rounds;
}

// Function to fetch job profiles for a company drive
function getJobProfiles($drive_id) {
    global $conn;
    $query = "
        SELECT jp.profile_name
        FROM campus_placement_job_profiles cpjp
        LEFT JOIN job_profiles jp ON cpjp.job_profile_id = jp.profile_id
        WHERE cpjp.campus_placement_info_id = $drive_id
        ORDER BY jp.profile_name
    ";
    $result = mysqli_query($conn, $query);
    $profiles = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $profiles[] = $row['profile_name'];
        }
    }
    return $profiles;
}

// Handle delete request
if (isset($_POST['drive_id'])) {
    $drive_id = intval($_POST['drive_id']);
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE sri FROM student_round_info sri JOIN company_rounds_info cri ON sri.company_round_info_id = cri.id WHERE cri.campus_placement_info_id = $drive_id");
        mysqli_query($conn, "DELETE FROM company_rounds_info WHERE campus_placement_info_id = $drive_id");
        mysqli_query($conn, "DELETE FROM campus_placement_job_profiles WHERE campus_placement_info_id = $drive_id");
        mysqli_query($conn, "DELETE FROM campus_drive_enroll WHERE campus_drive_info_id = $drive_id");
        mysqli_query($conn, "DELETE FROM campus_placement_info WHERE id = $drive_id");
        mysqli_commit($conn);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch company drive info
if (!isset($_GET['drive_id'])) die("Drive ID is missing.");
$drive_id = intval($_GET['drive_id']);
$company_query = "
    SELECT 
        cpi.id as driveId,
        cmi.id as companyId,
        cmi.company_name,
        cpi.*,
        bi.batch_start_year,
        bi.batch_end_year
    FROM campus_placement_info cpi
    JOIN company_info cmi ON cpi.company_info_id = cmi.id
    JOIN batch_info bi ON cpi.batch_info_id = bi.id
    WHERE cpi.id = $drive_id
";
$company_result = mysqli_query($conn, $company_query);
$company = mysqli_fetch_assoc($company_result);
if (!$company) die("Company not found.");

// Fetch rounds and job profiles
$rounds = getCompanyRounds($drive_id);
$jobProfiles = getJobProfiles($drive_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campus Drive Details</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
<?php $page_title="Campus Drive Details"; include('./navbar.php'); ?>

<div class="container mx-auto p-6">
    <!-- Buttons -->
    <div class="mb-4">
        <a href="javascript:history.back()" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all"> <i class="fa-solid fa-angle-left"></i> Back </a>
        <a href="campus_company_data_edit.php?drive_id=<?php echo $drive_id; ?>" 
           class="text-white bg-cyan-600 p-2 px-5 rounded-full hover:px-7 inline-block transition-all mr-3">
           <i class="fa-regular fa-pen-to-square"></i> Edit
        </a>

        <form id="deleteForm" method="POST" class="inline">
            <input type="hidden" name="drive_id" value="<?php echo $drive_id; ?>">
            <button type="button" id="deleteButton" class="text-white bg-red-500 p-2 px-5 rounded-full hover:px-7 inline-block transition-all">
                <i class="fa-regular fa-trash-can"></i> Delete
            </button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg drop-shadow-xl">
        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($company['company_name']); ?></h1>
        <p>Batch: <?php echo htmlspecialchars($company['batch_start_year'].' - '.$company['batch_end_year']); ?></p>
        <div class="rounded-full w-full h-1 mt-2 bg-slate-100"></div>

        <div class="grid grid-cols-2 gap-4 mt-6">
            <div>
                <h2 class="text-xl mb-2 font-semibold flex items-center"><div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>Date & Time</h2>
                <div class="pl-5 text-gray-700">
                    <?php
                    if (!empty($company['date']) && !empty($company['time'])) {
                        echo date("d/m/Y", strtotime($company['date'])) . " - " . date("g:i A", strtotime($company['time']));
                    } else { echo "Will be declared"; }
                    ?>
                </div>
            </div>
            <div>
                <h2 class="text-xl mb-2 font-semibold flex items-center"><div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>Work Location</h2>
                <div class="pl-5 text-gray-700"><?php echo htmlspecialchars($company['location'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <h2 class="text-xl mb-2 font-semibold flex items-center"><div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>Package</h2>
                <div class="pl-5 text-gray-700"><?php echo htmlspecialchars($company['package'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <h2 class="text-xl mb-2 font-semibold flex items-center"><div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>Job Profiles</h2>
                <div class="pl-5 text-gray-700">
                    <?php
                    if (!empty($jobProfiles)) {
                        echo implode(', ', array_map('htmlspecialchars', $jobProfiles));
                    } else {
                        echo "No job profiles found";
                    }
                    ?>
                </div>
            </div>
        </div>

        <h2 class="text-xl mt-6 mb-2 font-semibold flex items-center">
            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>Selection Process
        </h2>
        <?php if (!empty($rounds)): ?>
            <ul>
                <?php foreach ($rounds as $index => $round): ?>
                    <li class="mb-3 pl-5">
                        <strong><?php echo $index+1; ?>)</strong>
                        <span class="text-gray-700 ml-2">
                            <?php echo htmlspecialchars($round['round_name'] ?? 'N/A'); ?>
                            (<?php echo htmlspecialchars($round['mode'] ?? 'offline'); ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="pl-5 text-gray-700">No rounds found for this company.</p>
        <?php endif; ?>

        <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-3"></div>Other Info
        </h2>
        <div class="pl-5 text-gray-700"><?php echo nl2br(htmlspecialchars($company['other_info'] ?? 'N/A')); ?></div>
    </div>
</div>

<script>
document.getElementById('deleteButton')?.addEventListener('click', function() {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will permanently delete the campus drive and all related records!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('deleteForm');
            const formData = new FormData(form);
            fetch('', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Deleted!', 'Campus drive deleted successfully.', 'success')
                        .then(() => window.location.href='campus_drive.php');
                    } else {
                        Swal.fire('Error!', 'Failed to delete the campus drive.', 'error');
                    }
                }).catch(() => Swal.fire('Error!', 'Server error occurred.', 'error'));
        }
    });
});
</script>
</body>
</html>
