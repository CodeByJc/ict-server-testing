<?php
include('../../api/db/db_connection.php');

// Fetch company names for the dropdown
function getCompanies()
{
    global $conn;
    $query = "SELECT id, company_name FROM company_info ORDER BY company_name ASC";
    $result = mysqli_query($conn, $query);
    $companies = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $companies[] = $row;
    }
    return $companies;
}

$companies = getCompanies();

// Function to fetch mode options (Offline/Online)
function getModeOptions()
{
    return [
        'offline' => 'Offline',
        'online' => 'Online'
    ];
}

// Fetch the current year
$current_year = date("Y");

// Fetch all batches for the dropdown
function getBatches()
{
    global $conn;
    $query = "SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year ASC";
    $result = mysqli_query($conn, $query);
    $batches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = $row;
    }
    return $batches;
}

$batches = getBatches();


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
        <?php
        $page_title = "Add Campus Drive";
        include('./navbar.php');


        // Handle the creation of a new campus drive
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $company_id = $_POST['company_id'];
            $date = !empty($_POST['date']) ? $_POST['date'] : null;
            $time = !empty($_POST['time']) ? $_POST['time'] : null;
            $location = $_POST['location'];
            $job_profile = $_POST['job_profile'];
            $package = $_POST['package'];
            $other_info = $_POST['other_info'];
            $batch_id = intval($_POST['batch_id']);
            $round_names = $_POST['round_names'];
            $round_modes = $_POST['round_modes'];
            $round_indices = $_POST['round_index'];

    
            // Insert campus placement info
            $insert_query = "INSERT INTO campus_placement_info (company_info_id, date, time, location, job_profile, package, other_info, batch_info_id) 
                     VALUES ($company_id, " . ($date ? "'$date'" : "NULL") . ", " . ($time ? "'$time'" : "NULL") . ", '$location', '$job_profile', '$package', '$other_info', $batch_id)";

            if (mysqli_query($conn, $insert_query)) {
                $placement_id = mysqli_insert_id($conn);

                // Insert new rounds for the campus drive
                foreach ($round_names as $round_key => $round_name) {
                    $mode = $round_modes[$round_key];
                    $index = $round_indices[$round_key];
                    $insert_round_query = "INSERT INTO company_rounds_info (campus_placement_info_id, round_name, mode, round_index) 
                                   VALUES ('$placement_id', '$round_name', '$mode', $index)";
                    mysqli_query($conn, $insert_round_query);
                }

                echo "<script>
                Swal.fire({
                    title: 'Added!',
                    text: 'Campus drive and rounds have been added successfully.',
                    icon: 'success'
                }).then(function() {
                    window.location.href = 'campus_drive.php';
                });
            </script>";
                exit;
            } else {
                echo "Error inserting record: " . mysqli_error($conn);
            }
        }
        ?>

        <div class="container mx-auto p-6">
            <a href="javascript:history.back()" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-solid fa-angle-left"></i> Back
            </a>

            <form id="addForm" action="" method="POST" class="bg-white p-6 rounded-xl shadow-md">
                <div class="w-full md:w-1/2 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Select Company</label>
                    <select name="company_id" class="w-full p-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" required>
                        <option value="" disabled selected>Select a company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>">
                                <?php echo $company['company_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-wrap -mx-3">
                    <!-- Date Field -->
                    <div class="w-full md:w-1/6 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Date</label>
                        <input type="date" name="date" class="w-full p-3 border-2 rounded-xl">
                    </div>
                    <!-- Time Field -->
                    <div class="w-full md:w-1/6 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Time</label>
                        <input type="time" name="time" class="w-full p-3 border-2 rounded-xl">
                    </div>
                    <!-- Batch Dropdown -->
                    <div class="w-full md:w-1/6 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Batch</label>
                        <select name="batch_id" class="w-full p-3 border-2 rounded-xl" required>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>"
                                <?php echo ($batch['batch_end_year'] == $current_year) ? 'selected' : ''; ?>>
                                <?php echo $batch['batch_start_year'] . ' - ' . $batch['batch_end_year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div class="w-full md:w-1/2 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Work Location</label>
                        <input type="text" name="location" class="w-full p-3 border-2 rounded-xl" required>
                    </div>
                </div>
                <div class="flex flex-wrap -mx-3">
                    <div class="w-full md:w-1/2 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Job Profile</label>
                        <textarea name="job_profile" rows="3" class="w-full p-3 border-2 rounded-xl"></textarea>
                    </div>
                    <div class="w-full md:w-1/2 px-3 mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Package</label>
                        <textarea name="package" rows="3" class="w-full p-3 border-2 rounded-xl"></textarea>
                    </div>
                </div>
                <div class="w-full md:w-1/2 mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Selection Process Rounds</label>
                    <div id="newRoundsContainer"></div>
                    <button type="button" id="addRoundBtn" class="bg-cyan-500 text-sm font-bold text-white ml-10 px-3 p-1 rounded-full hover:scale-110 hover:bg-cyan-600 transition-all mt-3 mb-2">
                        + New Round
                    </button>
                </div>
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
        $(document).ready(function () {
            $('.select2').select2({
                theme: '',
                placeholder: "Select a company",
                allowClear: true
            });
        });


        // Function to update round indices after deletion or addition
        function updateRoundIndices() {
            const rounds = document.querySelectorAll('.flex.items-center.ml-4.mb-2');
            let index = 1;

            rounds.forEach((round) => {
                const roundSpan = round.querySelector('.text-cyan-600');
                roundSpan.textContent = `${index}.`; // Update the index text

                const roundInput = round.querySelector('input[name^="round_index"]');
                if (roundInput) {
                    roundInput.value = index; // Update the round_index value
                }

                index++;
            });
        }

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

        let roundCount = 0; // Track the number of rounds

        document.getElementById('addRoundBtn').addEventListener('click', function() {
            roundCount++;

            // Create new round input fields
            let newRoundHTML = `
        <div class="flex items-center ml-4 mb-2" id="round-new-${roundCount}">
            <span class="mr-2 text-cyan-600 font-bold">${roundCount}.</span>
            <input type="text" name="round_names[new-${roundCount}]" class="p-2 border-2 rounded-xl flex-1" placeholder="Enter round name" required>
            
            <select name="round_modes[new-${roundCount}]" class="p-2 border-2 rounded-xl ml-2" required>
                <option value="offline">Offline</option>
                <option value="online">Online</option>
            </select>

            <input type="hidden" name="round_index[new-${roundCount}]" value="${roundCount}">
            
            <button type="button" class="text-red-500 ml-3" onclick="removeRound(${roundCount})">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
            document.getElementById('newRoundsContainer').insertAdjacentHTML('beforeend', newRoundHTML);
        });

        // Function to remove a round input
        function removeRound(roundNumber) {
            document.getElementById(`round-new-${roundNumber}`).remove();
            updateRoundIndices();
        }
    </script>
</body>

</html>