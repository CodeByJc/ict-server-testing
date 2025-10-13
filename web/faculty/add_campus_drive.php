<?php
include('../../api/db/db_connection.php');

// Fetch company names for the dropdown
function getCompanies() {
    global $conn;
    $query = "SELECT id, company_name FROM company_info ORDER BY company_name ASC";
    $result = mysqli_query($conn, $query);
    $companies = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $companies[] = $row;
    }
    return $companies;
}

// Fetch all batches for the dropdown
function getBatches() {
    global $conn;
    $query = "SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year ASC";
    $result = mysqli_query($conn, $query);
    $batches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = $row;
    }
    return $batches;
}

// Fetch all job profiles
function getJobProfiles() {
    global $conn;
    $query = "SELECT profile_id, profile_name FROM job_profiles ORDER BY profile_name ASC";
    $result = mysqli_query($conn, $query);
    $jobProfiles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jobProfiles[] = $row;
    }
    return $jobProfiles;
}

// Fetch all company rounds
function getCompanyRounds() {
    global $conn;
    $query = "SELECT round_id, round_name FROM company_rounds ORDER BY round_name ASC";
    $result = mysqli_query($conn, $query);
    $companyRounds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $companyRounds[] = $row;
    }
    return $companyRounds;
}

$companies = getCompanies();
$batches = getBatches();
$jobProfiles = getJobProfiles();
$companyRounds = getCompanyRounds();

// Current year
$current_year = date("Y");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization
    $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
    $date = !empty($_POST['date']) ? $_POST['date'] : null;
    $time = !empty($_POST['time']) ? $_POST['time'] : null;
    $location = isset($_POST['location']) ? mysqli_real_escape_string($conn, trim($_POST['location'])) : '';
    $package = isset($_POST['package']) ? mysqli_real_escape_string($conn, trim($_POST['package'])) : '';
    $other_info = isset($_POST['other_info']) ? mysqli_real_escape_string($conn, trim($_POST['other_info'])) : '';
    $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : null;

    // Arrays (may be absent)
    $job_profile_ids = isset($_POST['job_profile_ids']) && is_array($_POST['job_profile_ids']) ? $_POST['job_profile_ids'] : [];
    $round_ids = isset($_POST['round_ids']) && is_array($_POST['round_ids']) ? $_POST['round_ids'] : [];
    $round_modes = isset($_POST['round_modes']) && is_array($_POST['round_modes']) ? $_POST['round_modes'] : [];
    $round_indices = isset($_POST['round_index']) && is_array($_POST['round_index']) ? $_POST['round_index'] : [];

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Insert into campus_placement_info (note: job_profile column removed in schema)
        $date_sql = $date ? "'" . mysqli_real_escape_string($conn, $date) . "'" : "NULL";
        $time_sql = $time ? "'" . mysqli_real_escape_string($conn, $time) . "'" : "NULL";
        $batch_sql = $batch_id !== null ? intval($batch_id) : "NULL";

        $insert_query = "
            INSERT INTO campus_placement_info
            (company_info_id, date, time, location, package, other_info, batch_info_id)
            VALUES (
                " . intval($company_id) . ",
                $date_sql,
                $time_sql,
                '" . $location . "',
                '" . $package . "',
                '" . $other_info . "',
                $batch_sql
            )
        ";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception("Error inserting campus_placement_info: " . mysqli_error($conn));
        }

        $placement_id = mysqli_insert_id($conn);

        // Insert job profiles mapping (campus_placement_job_profiles)
        if (!empty($job_profile_ids)) {
            $stmt_values = [];
            foreach ($job_profile_ids as $profile_id_raw) {
                $profile_id = intval($profile_id_raw);
                if ($profile_id > 0) {
                    $stmt_values[] = "($placement_id, $profile_id)";
                }
            }
            if (!empty($stmt_values)) {
                $profiles_query = "INSERT INTO campus_placement_job_profiles (campus_placement_info_id, job_profile_id) VALUES " . implode(',', $stmt_values);
                if (!mysqli_query($conn, $profiles_query)) {
                    throw new Exception("Error inserting job profiles: " . mysqli_error($conn));
                }
            }
        }

        // Insert company rounds info (company_rounds_info)
        if (!empty($round_ids)) {
            $stmt_values = [];
            foreach ($round_ids as $key => $round_id_raw) {
                $round_id = intval($round_id_raw);
                // round_modes and round_indices may not have same keys -- validate
                $mode_raw = isset($round_modes[$key]) ? $round_modes[$key] : 'offline';
                $mode = ($mode_raw === 'online') ? 'online' : 'offline';
                $index = isset($round_indices[$key]) ? intval($round_indices[$key]) : ($key + 1);

                if ($round_id > 0) {
                    $stmt_values[] = "($placement_id, $round_id, '" . mysqli_real_escape_string($conn, $mode) . "', $index)";
                }
            }
            if (!empty($stmt_values)) {
                $rounds_query = "INSERT INTO company_rounds_info (campus_placement_info_id, company_round_id, mode, round_index) VALUES " . implode(',', $stmt_values);
                if (!mysqli_query($conn, $rounds_query)) {
                    throw new Exception("Error inserting company rounds info: " . mysqli_error($conn));
                }
            }
        }

        // Commit transaction
        mysqli_commit($conn);
        header("Location: campus_drive.php");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        // Show error (you can replace this with a nicer error handling)
        $errMsg = mysqli_real_escape_string($conn, $e->getMessage());
        echo "<script>
            Swal.fire({
                title: 'Error',
                text: 'There was an error saving the campus drive: " . addslashes($errMsg) . "',
                icon: 'error'
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Campus Drive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
    <?php $page_title = "Add Campus Drive"; include('./navbar.php'); ?>

    <div class="container mx-auto p-6">
        <a href="javascript:history.back()" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
            <i class="fa-solid fa-angle-left"></i> Back
        </a>

        <form id="addForm" action="" method="POST" class="bg-white p-6 rounded-xl shadow-md">
            <!-- Company -->
            <div class="w-full md:w-1/2 mb-4">
                <label class="block text-gray-700 font-bold mb-2">Select Company</label>
                <select name="company_id" class="w-full p-3 border-2 border-gray-300 rounded-xl" required>
                    <option value="" disabled selected>Select a company</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo htmlspecialchars($company['id']); ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date, Time, Batch, Location -->
            <div class="flex flex-wrap -mx-3">
                <div class="w-full md:w-1/6 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Date</label>
                    <input type="date" name="date" class="w-full p-3 border-2 rounded-xl">
                </div>
                <div class="w-full md:w-1/6 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Time</label>
                    <input type="time" name="time" class="w-full p-3 border-2 rounded-xl">
                </div>
                <div class="w-full md:w-1/6 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Batch</label>
                    <select name="batch_id" class="w-full p-3 border-2 rounded-xl" required>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo htmlspecialchars($batch['id']); ?>" <?php echo ($batch['batch_end_year']==$current_year)?'selected':''; ?>>
                                <?php echo htmlspecialchars($batch['batch_start_year'].' - '.$batch['batch_end_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-full md:w-1/2 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Work Location</label>
                    <input type="text" name="location" class="w-full p-3 border-2 rounded-xl" required>
                </div>
            </div>

            <!-- Job Profile & Package -->
            <div class="flex flex-wrap -mx-3">
                <div class="w-full md:w-1/2 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Job Profiles</label>
                    <div class="border-2 rounded-xl p-3 max-h-64 overflow-y-auto">
                        <?php foreach ($jobProfiles as $jp): ?>
                            <label class="flex items-center mb-2">
                                <input type="checkbox" name="job_profile_ids[]" value="<?php echo htmlspecialchars($jp['profile_id']); ?>" class="mr-2">
                                <span><?php echo htmlspecialchars($jp['profile_name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-gray-500">Select multiple job profiles</small>
                </div>

                <div class="w-full md:w-1/2 px-3 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Package</label>
                    <textarea name="package" rows="3" class="w-full p-3 border-2 rounded-xl"></textarea>
                </div>
            </div>

            <!-- Selection Rounds -->
            <div class="w-full md:w-1/2 mb-4">
                <label class="block text-gray-700 font-bold mb-2">Selection Process Rounds</label>
                <div id="newRoundsContainer"></div>
                <button type="button" id="addRoundBtn" class="bg-cyan-500 text-sm font-bold text-white ml-10 px-3 p-1 rounded-full hover:scale-110 hover:bg-cyan-600 transition-all mt-3 mb-2">
                    + New Round
                </button>
            </div>

            <!-- Other Info -->
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Other Info</label>
                <textarea name="other_info" rows="10" class="w-full p-3 border-2 rounded-xl"></textarea>
            </div>

            <button type="button" id="saveBtn" class="bg-cyan-600 text-white px-5 p-3 rounded-full hover:px-7 font-bold hover:bg-cyan-700 transition-all">
                Save Changes
            </button>
        </form>
    </div>
</div>

<script>
let roundCount = 0;
const companyRounds = <?php echo json_encode($companyRounds); ?>;

// Add new round
document.getElementById('addRoundBtn').addEventListener('click', function() {
    roundCount++;
    let optionsHTML = '';
    companyRounds.forEach(r => {
        optionsHTML += `<option value="${r.round_id}">${r.round_name}</option>`;
    });

    let newRoundHTML = `
    <div class="flex items-center ml-4 mb-2" id="round-new-${roundCount}">
        <span class="mr-2 text-cyan-600 font-bold">${roundCount}.</span>
        <select name="round_ids[]" class="p-2 border-2 rounded-xl flex-1" required>${optionsHTML}</select>
        <select name="round_modes[]" class="p-2 border-2 rounded-xl ml-2" required>
            <option value="offline">Offline</option>
            <option value="online">Online</option>
        </select>
        <input type="hidden" name="round_index[]" value="${roundCount}">
        <button type="button" class="text-red-500 ml-3" onclick="removeRound(${roundCount})">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>`;
    document.getElementById('newRoundsContainer').insertAdjacentHTML('beforeend', newRoundHTML);
});

// Remove round
function removeRound(roundNumber) {
    const el = document.getElementById(`round-new-${roundNumber}`);
    if (el) el.remove();
    updateRoundIndices();
}

// Update round indices
function updateRoundIndices() {
    const rounds = document.querySelectorAll('#newRoundsContainer > div');
    let index = 1;
    rounds.forEach(round => {
        const span = round.querySelector('span');
        const input = round.querySelector('input[name="round_index[]"]');
        if (span) span.textContent = `${index}.`;
        if (input) input.value = index;
        index++;
    });
}

// Confirm save
document.getElementById('saveBtn').addEventListener('click', function() {
    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to save the changes?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, save it!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('addForm').submit();
        }
    });
});
</script>
</body>
</html>
