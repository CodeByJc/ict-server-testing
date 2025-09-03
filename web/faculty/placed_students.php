<?php
include('../../api/db/db_connection.php');

// Fetch all batches
$batches_query = "SELECT * FROM batch_info";
$batches_result = mysqli_query($conn, $batches_query);

// Fetch the current year
$current_year = date("Y");  
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placed Students</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Placed Students";
        include('./navbar.php');


        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['placement_id'])) {
            $placement_id = intval($_POST['placement_id']);
        
            $delete_query = "DELETE FROM placed_student_info WHERE id = ?";
            $stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $placement_id);
        
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Student record deleted successfully.',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            window.location.href = 'placed_students.php';
                        });
                      </script>";
            } else {
                echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Failed to delete student record.',
                            confirmButtonColor: '#d33'
                        }).then(() => {
                            window.history.back();
                        });
                      </script>";
            }
        
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        ?>
        
        <div class="p-6">
            <div class="mb-4 flex items-center">
                <a href="add_placed_students.php" class="shadow-lg bg-cyan-500 px-6 hover:px-8 text-white p-2 hover:bg-cyan-600 rounded-md   transition-all">
                    Add Student
                </a>
                <input type="text" id="search" class="shadow-lg border-2 ml-10 pl-4 p-2 rounded-md w-1/2" 
                       placeholder="Search Students/Companies..." onkeyup="searchTable()">
                
                <!-- Batch Dropdown -->
                <select id="batchDropdown" class="ml-10 shadow-lg border-2 px-5 p-2 rounded-md" onchange="fetchPlacedStudents()">
                    <?php 
                    while ($batch = mysqli_fetch_assoc($batches_result)):
                        $selected = ($batch['batch_end_year'] == $current_year) ? "selected" : "";
                    ?>
                        <option value="<?php echo $batch['id']; ?>" <?php echo $selected; ?>>
                            <?php echo $batch['batch_start_year'] . '-' . $batch['batch_end_year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <table id="company-table" class="min-w-full bg-white border-2 border-gray-300">
                <thead>
                    <tr>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">No</th>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">Student Name</th>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">Company Name</th>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">Package (LPA)</th>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">Date</th>
                        <th class="border-2 border-gray-300 bg-gray-700 text-white px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody> <!-- Table will be populated dynamically -->
            </table>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", fetchPlacedStudents);

        function fetchPlacedStudents() {
            const batchId = document.getElementById("batchDropdown").value;

            fetch(`fetch_placed_students.php?batch_id=${batchId}`)
                .then(response => response.json())
                .then(data => {
                    let tableBody = document.querySelector("#company-table tbody");
                    tableBody.innerHTML = ""; 

                    if (data.length === 0) {
                        tableBody.innerHTML = "<tr><td colspan='6' class='text-center py-2'>No records found</td></tr>";
                        return;
                    }

                    let studentCounts = {};
                    data.forEach(row => {
                        studentCounts[row.student_name] = (studentCounts[row.student_name] || 0) + 1;
                    });

                    let renderedStudents = {};
                    let counter = 1;

                    data.forEach(row => {
                        let tr = document.createElement("tr");

                        if (!renderedStudents[row.student_name]) {
                            tr.innerHTML += `
                                <td rowspan="${studentCounts[row.student_name]}" class="border-2 border-gray-300 px-4 py-2 text-center">${counter}</td>
                                <td rowspan="${studentCounts[row.student_name]}" class="border-2 border-gray-300 px-4 py-2">${row.student_name}</td>
                            `;
                            renderedStudents[row.student_name] = true;
                            counter++;
                        }

                        tr.innerHTML += `
                            <td class="border-2 border-gray-300 px-4 py-2">${row.company_name}</td>
                            <td class="border-2 border-gray-300 px-4 py-2 text-center">
                                ${row.package_start == row.package_end ? row.package_start : row.package_start + " - " + row.package_end}
                            </td>
                            <td class="border-2 border-gray-300 px-4 py-2 text-center">${row.date ? new Date(row.date).toLocaleDateString("en-GB") : "-"}</td>
                            <td class="border-2 border-gray-300 px-4 py-2 text-center">
                                <button type="button" class="text-red-500 mr-2" onclick="confirmDelete(${row.placement_id})">Delete</button>
                            </td>
                        `;


                        tableBody.appendChild(tr);
                    });
                })
                .catch(error => console.error("Error fetching data:", error));
        }

        function confirmDelete(placementId) {
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement("form");
                    form.method = "POST";
                    form.action = "";
                    let input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "placement_id";
                    input.value = placementId;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function searchTable() {
            const searchInput = document.getElementById('search').value.toLowerCase();
            const rows = document.querySelectorAll('#company-table tbody tr');
            rows.forEach(row => {
                const studentName = row.cells[1]?.textContent.toLowerCase() || "";
                const companyName = row.cells[2]?.textContent.toLowerCase() || "";
                const packageLPA = row.cells[3]?.textContent.toLowerCase() || "";
                const date = row.cells[4]?.textContent.toLowerCase() || "";
                if (studentName.includes(searchInput) || companyName.includes(searchInput)||packageLPA.includes(searchInput) || date.includes(searchInput)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    
    </script>
</body>
</html>
