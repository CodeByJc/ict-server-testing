<?php
include('../../api/db/db_connection.php');

// Function to fetch rounds for a company
function getCompanyRounds($drive_id) {
    global $conn;
    $query = "SELECT * FROM company_rounds_info WHERE campus_placement_info_id = $drive_id ORDER BY round_index";
    $result = mysqli_query($conn, $query);
    $rounds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rounds[] = $row;
    }
    return $rounds;
}

// Check if company_id is passed in the URL
if (isset($_GET['drive_id'])) {
    $drive_id = intval($_GET['drive_id']);

    // Fetch company information from the database
    $company_query = "SELECT cpi.id as driveId, cmi.id as companyId, cmi.company_name, cpi.*, bi.id as batch_id, bi.batch_start_year, bi.batch_end_year
                      FROM campus_placement_info cpi
                      JOIN company_info cmi ON cpi.company_info_id = cmi.id
                      JOIN batch_info bi ON cpi.batch_info_id = bi.id
                      WHERE cpi.id = $drive_id;";
    $company_result = mysqli_query($conn, $company_query);
    $company = mysqli_fetch_assoc($company_result);

    if (!$company) {
        die("Company not found.");
    }

    // Fetch rounds for the company
    $rounds = getCompanyRounds($drive_id);
} else {
    die("Company ID is missing.");
}

if (isset($_POST['drive_id'])) {
    $drive_id = intval($_POST['drive_id']);

    // Delete related records from company_rounds_info
    $delete_rounds_query = "DELETE FROM company_rounds_info WHERE campus_placement_info_id = $drive_id";
    $delete_rounds_result = mysqli_query($conn, $delete_rounds_query);

    // Delete record from campus_placement_info
    $delete_drive_query = "DELETE FROM campus_placement_info WHERE id = $drive_id";
    $delete_drive_result = mysqli_query($conn, $delete_drive_query);

    if ($delete_drive_result && $delete_rounds_result) {
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Info</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include('./sidebar.php'); ?>

    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php $page_title = "Campus Drive Company Information"; include('./navbar.php'); ?>

        <div class="container mx-auto p-6">
            <!-- Back Button -->
            <a href="campus_drive.php" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4  hover:px-7 inline-block transition-all">
                <i class="fa-solid fa-angle-left"></i> Back
            </a>

            <!-- Edit Button -->
            <a href="campus_company_data_edit.php?drive_id=<?php echo $drive_id; ?>" class="ml-3 text-white bg-cyan-600 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-regular fa-pen-to-square"></i> Edit
            </a>

            <!-- View Student List Button -->
            <a href="company_students_list.php?drive_id=<?php echo $drive_id; ?>&company=<?php echo $company['company_name']; ?>" class="ml-3 text-white bg-cyan-600 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-solid fa-list-ul"></i> View Students List
            </a>


            <!-- Delete Form -->
            <form id="deleteForm" method="POST" action="" class="inline">
                <input type="hidden" name="drive_id" value="<?php echo $drive_id; ?>">
                <button type="button" id="deleteButton" class="ml-5 text-white bg-red-500 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                    <i class="fa-regular fa-trash-can"></i> Delete
                </button>
            </form>

            
            <!-- Display company information -->
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <h1 class="text-3xl font-bold">
                    <?php echo $company['company_name']; ?>
                </h1>
                Batch: <?php echo $company['batch_start_year']; ?> - <?php echo $company['batch_end_year']; ?>

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
                            <?php
                            if ($company['date'] && $company['time']) {
                                echo $company['date'] ? date("d/m/Y", strtotime($company['date'])) : "";
                                echo " - ";
                                echo $company['time'] ? date("g:i A", strtotime($company['time'])) : "";
                            } else {
                                echo "Will be declared";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Work Location -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Work Location</span>
                        </h2>
                        <div class="pl-5 text-gray-700">
                            <?php echo $company['location']; ?>
                        </div>
                    </div>

                    <!-- Job Profile -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Job Profile</span>
                        </h2>
                        <div class="pl-5 text-gray-700">
                            <?php echo nl2br(htmlspecialchars($company['job_profile'])); ?>
                        </div>
                    </div>

                    <!-- Package -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                            <span>Package</span>
                        </h2>
                        <div class="pl-5 text-gray-700">
                            <?php echo nl2br(htmlspecialchars($company['package'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Display rounds information -->
                <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
                    <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-2"></div>
                    <span>Selection Process</span>
                </h2>

                <?php if (!empty($rounds)): ?>
                    <ul>
                        <?php foreach ($rounds as $index => $round): ?>
                            <li class="mb-3 pl-5">
                                <div class="flex items-center">
                                    <strong class="mr-2"><?php echo $index + 1; ?>)</strong> <!-- Display index of the loop -->
                                    <p class="text-gray-700">
                                        <?php echo htmlspecialchars($round['round_name']); ?> 
                                        (<?php echo $round['mode']; ?>)
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="pl-5">No rounds found for this company.</p>
                <?php endif; ?>

                <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
                    <div class="pl-1.5 py-3 bg-cyan-600 rounded mr-3"></div>
                    <span>Other info</span>
                </h2>
                <div class="pl-5 text-gray-700">
                    <?php echo nl2br(htmlspecialchars($company['other_info'])); ?>
                </div>

            </div>
        </div>
    </div>

    <script>
       document.getElementById('deleteButton').addEventListener('click', function() {
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
            // Make sure the form submits correctly
            var form = document.getElementById('deleteForm');
            var formData = new FormData(form);

            // Send the form data using fetch or XMLHttpRequest (AJAX request)
            fetch(form.action, {
                method: form.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Deleted!', 'The campus drive has been deleted.', 'success')
                    .then(() => {
                        window.location.href = 'campus_drive.php';  // Redirect after deletion
                    });
                } else {
                    Swal.fire('Error!', 'There was an issue deleting the campus drive.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error!', 'Failed to delete the campus drive.', 'error');
            });
        }
    });
});

    </script>
</body>
</html>
