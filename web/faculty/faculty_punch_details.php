<?php
include('../../api/db/db_connection.php');
$faculty_id = intval($_GET['faculty_id']);
$faculty_name = strval($_GET['faculty_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Punch</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">

<?php
$page_title = "Punch details of ".$faculty_name;
include('./navbar.php');
?>

<div class="p-6">
    <form id="punch-table-form" class="rounded-lg" method="POST">
        <div class="py-6">
            <a href="faculty_details.php?faculty_id=<?php echo $faculty_id; ?>" class="bg-gray-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-gray-600 rounded-md mb-6 transition-all">Back</a>
            <input type="text" id="search" class="shadow-lg ml-5 pl-4 p-2 rounded-md w-1/2" placeholder="Search by date (dd-mm-yyyy)..." onkeyup="searchTable()">
        </div>
        <table id="punch-table" class="min-w-full bg-white shadow-lg rounded-md">
            <thead>
                <tr class="bg-gray-700 text-white">
                    <th class="border px-4 py-2 text-center">No</th>
                    <th class="border px-4 py-2 text-center">Punch Date</th>
                    <th class="border px-4 py-2 text-center">Punch In</th>
                    <th class="border px-4 py-2 text-center">Punch Out</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT * FROM faculty_attendance_info WHERE faculty_info_id = $faculty_id ORDER BY date DESC";
                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0) {
                    $counter = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        $formatted_date = date("d-m-Y", strtotime($row['date']));
                        $punch_in = $row['punch_in'] ? date("h:i A", strtotime($row['punch_in'])) : 'N/A';
                        $punch_out = $row['punch_out'] ? date("h:i A", strtotime($row['punch_out'])) : 'No Punch Out';

                        echo "<tr>
                                <td class='border px-4 py-2 text-center'>{$counter}</td>
                                <td class='border px-4 py-2 text-center'>{$formatted_date}</td>
                                <td class='border px-4 py-2 text-center'>{$punch_in}</td>
                                <td class='border px-4 py-2 text-center'>{$punch_out}</td>
                              </tr>";
                        $counter++;
                    }
                }
                ?>
            </tbody>
        </table>
    </form>
</div>

<script>
$(document).ready(function () {
    $('#punch-table').DataTable({
        paging: false,
        info: false,
        searching: false,
        columnDefs: [
            { orderable: false, targets: 0 }
        ],
        // Prevent DataTables from throwing an alert
        language: {
            emptyTable: "No punch records found" // Custom message, but won't be used due to our manual row
        }
    });
});

// Real-time search function by date
function searchTable() {
    const searchInput = document.getElementById('search').value.toLowerCase();
    const rows = document.querySelectorAll('#punch-table tbody tr');

    rows.forEach(row => {
        const dateCell = row.cells[3].textContent.toLowerCase(); // Punch Date column

        if (searchInput === '' || dateCell.includes(searchInput)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>