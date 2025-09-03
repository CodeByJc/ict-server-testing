<?php
include('../../api/db/db_connection.php');

$leaves_query = "SELECT li.*, CONCAT(si.first_name, ' ', si.last_name) AS student_name  
                FROM leave_info li  
                JOIN student_info si ON li.student_info_id = si.id  
                ORDER BY (li.leave_status = 'pending') DESC, li.created_at DESC";
$leaves_result = mysqli_query($conn, $leaves_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Leave</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Students Leave";
        include('./navbar.php');
        ?>

        <div class="mt-6">
            <!-- Search Bar -->
            <input type="text" id="search" class="ml-5 shadow-lg pl-4 p-2 rounded-md w-1/2" placeholder="Search by student name or date..." onkeyup="searchLeaves()">
        </div>

        <div class="p-5">
            <table id="leaves-table" class="min-w-full bg-white shadow-md rounded-md table-fixed">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="border px-4 py-2 rounded-tl-md w-4/12">Student Name</th>
                        <th class="border px-4 py-2 w-3/12">Created Date</th>
                        <th class="border px-4 py-2 w-2/12">Created Time</th>
                        <th class="border px-4 py-2 rounded-tr-md w-3/12">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($leave = mysqli_fetch_assoc($leaves_result)): ?>
                        <tr class="leave-item hover:bg-gray-200 hover:font-bold cursor-pointer transition-all" onclick="window.location.href='student_leave_details.php?leave_id=<?php echo $leave['id']; ?>'">
                            <td class="border px-4 py-2 <?php echo ($leave['leave_status'] == 'pending') ? 'font-bold' : ''; ?>">
                                <?php echo htmlspecialchars($leave['student_name']); ?>
                            </td>
                            <td class="border px-4 py-2 text-center">
                                <?php echo date("d/m/Y", strtotime($leave['created_at'])); ?>
                            </td>
                            <td class="border px-4 py-2 text-center">
                                <?php echo date("g:i A", strtotime($leave['created_at'])); ?>
                            </td>
                            <td class="border px-4 py-2 text-center">
                                <?php
                                $bgColor = ($leave['leave_status'] == "pending") ? "bg-yellow-500" : (($leave['leave_status'] == "approved") ? "bg-green-500" : "bg-red-500");
                                ?>
                                <span class="p-1 px-2 <?php echo $bgColor; ?> font-bold text-sm rounded-full text-white">
                                    <?php echo strtoupper($leave['leave_status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Real-time search function for leaves by name and date
        function searchLeaves() {
            const searchInput = document.getElementById('search').value.toLowerCase();
            const leaves = document.querySelectorAll('.leave-item');

            leaves.forEach(leave => {
                const studentName = leave.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const createdDate = leave.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (studentName.includes(searchInput) || createdDate.includes(searchInput)) {
                    leave.style.display = '';
                } else {
                    leave.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>