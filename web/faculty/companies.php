<?php
include('../../api/db/db_connection.php');

// Handle Add/Edit company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_name'], $_POST['company_type'], $_POST['company_website'], $_POST['company_linkedin'])) {
    $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $company_type = mysqli_real_escape_string($conn, $_POST['company_type']);
    $company_website = mysqli_real_escape_string($conn, $_POST['company_website']);
    $company_linkedin = mysqli_real_escape_string($conn, $_POST['company_linkedin']);

    if ($company_id) {
        // Update an existing company
        $update_query = "UPDATE company_info SET company_name = '$company_name', company_type = '$company_type', company_website = '$company_website', company_linkedin = '$company_linkedin' WHERE id = $company_id";
        if (mysqli_query($conn, $update_query)) {
            // Redirect to the same page to avoid resubmission on refresh
            header('Location: companies.php?status=updated');
            exit;
        }
    } else {
        // Add a new company
        $insert_query = "INSERT INTO company_info (company_name, company_type, company_website, company_linkedin) VALUES ('$company_name', '$company_type', '$company_website', '$company_linkedin')";
        if (mysqli_query($conn, $insert_query)) {
            // Redirect to the same page to avoid resubmission on refresh
            header('Location: companies.php?status=added');
            exit;
        }
    }
}

// Handle delete request for multiple companies
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
//     $ids = $_POST['selected_ids'];
//     if (!empty($ids)) {
//         $id_list = implode(',', $ids);
//         $delete_query = "DELETE FROM company_info WHERE id IN ($id_list)";
//         if (mysqli_query($conn, $delete_query)) {
//             header('Location: companies.php?status=deleted');
//             exit;
//         }
//     } else {
//         header('Location: companies.php?status=error');
//         exit;
//     }
// }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies</title>
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
        $page_title = "Companies";
        include('./navbar.php');

        // Show SweetAlert notifications based on query string
        if (isset($_GET['status'])) {
            $status = $_GET['status'];
            echo "<script>";
            if ($status === 'added') {
                echo "Swal.fire('Added!', 'company has been added successfully.', 'success');";
            } elseif ($status === 'updated') {
                echo "Swal.fire('Updated!', 'company has been updated successfully.', 'success');";
            } elseif ($status === 'deleted') {
                echo "Swal.fire('Deleted!', 'Selected company have been deleted.', 'success');";
            } elseif ($status === 'error') {
                echo "Swal.fire('Error!', 'No company were selected for deletion.', 'error');";
            }
            echo "</script>";
        }
        ?>
        <div class="p-6">
           
            
            <!-- Search bar for real-time search -->
            <div>
                <button onclick="openAddEditPopup()" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2  hover:bg-cyan-600 rounded-md mb-6 transition-all">Add Company</button>
                <input type="text" id="search" class="shadow-lg ml-5 pl-4 p-2 rounded-md w-1/2" placeholder="Search Companies..." onkeyup="searchTable()">
            </div>

            <form id="company-table-form" method="POST">
                <input type="hidden" name="delete_selected" value="1">
                <table id="company-table" class="min-w-full bg-white shadow-md rounded-md">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2  rounded-tl-md">No</th>
                            <th class="border px-4 py-2">Company Name</th>
                            <th class="border px-4 py-2">Type</th>
                            <th class="border px-4 py-2">Website</th>
                            <th class="border px-4 py-2">Linked In</th>
                            <th class="border px-4 py-2 rounded-tr-md">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM company_info ORDER BY company_name";
                        $result = mysqli_query($conn, $query);

                        if (mysqli_num_rows($result) > 0) {
                            $counter = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                <td class='border px-4 py-2 text-center'>{$counter}</td>
                                <td class='border px-4 py-2'>{$row['company_name']}</td>
                                <td class='border px-4 py-2 text-center'>{$row['company_type']}</td>
                                <td class='border px-4 py-2 text-center'>";
                                if (!empty($row['company_website'])) {
                                    echo "<a href='{$row['company_website']}' target='_blank' class='website-btn inline-block px-4 py-1 bg-transparent text-orange-500 border border-orange-500 rounded-full transition hover:bg-orange-500 hover:text-white'>Website</a>";
                                } else {
                                    echo "<button class='inline-block px-4 py-1 text-gray-500 border border-gray-500 rounded-full cursor-not-allowed'>Website</button>";
                                }
                                echo "</td>
                                <td class='border px-4 py-2 text-center'>";
                                if (!empty($row['company_linkedin'])) {
                                    echo "<a href='{$row['company_linkedin']}' target='_blank' class='linkedin-btn inline-block px-4 py-1 bg-transparent text-blue-600 border border-blue-600 rounded-full transition hover:bg-blue-600 hover:text-white'>LinkedIn</a>";
                                } else {
                                    echo "<div class='inline-block px-4 py-1 text-gray-500 border border-gray-500 rounded-full cursor-not-allowed'>LinkedIn</div>";
                                }
                                echo "</td>
                                <td class='border px-4 py-2 text-center'>
                                    <button type='button' onclick='openAddEditPopup({$row['id']}, \"{$row['company_name']}\", \"{$row['company_type']}\", \"{$row['company_website']}\", \"{$row['company_linkedin']}\")' class='text-blue-500 mr-2'>Edit</button>
                                </td>
                            </tr>";
                                $counter++;
                            }
                        } else {
                            echo "<tr><td colspan='5' class='border px-4 py-2 text-center'>No companies found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </form>
        </div>
        <div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center">
            <div class="bg-white rounded-lg p-6 w-96">
                <h2 id="popup-title" class="text-xl font-bold mb-4">Add/Edit Company</h2>
                <form id="popup-form" action="companies.php" method="POST">
                    <input type="hidden" name="company_id" id="company_id">
                    <div class="mb-4">
                        <label for="company_name" class="block text-sm font-medium mb-1">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" class="border-2 rounded-md p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="company_type" class="block text-sm font-medium mb-1">Company Type *</label>
                        <select id="company_type" name="company_type" class="border-2 rounded-md p-2 w-full" required>
                            <option value="Gujarat Based">Gujarat Based</option>
                            <option value="India Based">India Based</option>
                            <option value="MNC">MNC</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="company_website" class="block text-sm font-medium mb-1">Company Website URL *</label>
                        <input type="text" id="company_website" name="company_website" class="border-2 rounded-md p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="company_linkedin" class="block text-sm font-medium mb-1">Company Linkedin URL</label>
                        <input type="text" id="company_linkedin" name="company_linkedin" placeholder="(Optional)" class="border-2 rounded-md p-2 w-full">
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 hover:bg-gray-600 text-white p-2 rounded-full">Cancel</button>
                        <button type="submit" id="popup-submit" class="pl-6 pr-6 bg-cyan-500 hover:bg-cyan-600 text-white p-2 rounded-full">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            // Function to open the Add/Edit popup
            function openAddEditPopup(id = null, name = '', type = '', website = '', linkedin = '') {
                document.getElementById('popup-title').innerText = id ? 'Edit Company' : 'Add Company';
                document.getElementById('company_id').value = id || '';
                document.getElementById('company_name').value = name || '';
                document.getElementById('company_type').value = type || 'Gujarat Based';
                document.getElementById('company_website').value = website || '';
                document.getElementById('company_linkedin').value = linkedin || '';
                document.getElementById('popup-modal').classList.remove('hidden');
            }

            // Function to close the popup
            function closePopup() {
                document.getElementById('popup-modal').classList.add('hidden');
            }

            // Real-time search function
            function searchTable() {
                const searchInput = document.getElementById('search').value.toLowerCase();
                const rows = document.querySelectorAll('#company-table tbody tr');
                rows.forEach(row => {
                    const companyName = row.cells[1].textContent.toLowerCase();
                    const companyType = row.cells[2].textContent.toLowerCase();
                    const companyWebsite = row.cells[3].textContent.toLowerCase();
                    const companyLinkedIn = row.cells[4].textContent.toLowerCase();

                    if (companyName.includes(searchInput) || companyType.includes(searchInput) || companyWebsite.includes(searchInput) || companyLinkedIn.includes(searchInput)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        </script>
    </div>
</body>

</html>
