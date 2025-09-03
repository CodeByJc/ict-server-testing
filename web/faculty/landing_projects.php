<?php
include('../../api/db/db_connection.php');

// Handle Add/Edit video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['video_link'])) {
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $video_link = mysqli_real_escape_string($conn, $_POST['video_link']);

    if ($project_id) {
        // Update an existing project
        $update_query = "UPDATE landing_project_links SET title = ?, video_link = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, 'ssi', $title, $video_link, $project_id);
        if (mysqli_stmt_execute($stmt)) {
            header('Location: landing_projects.php?status=updated');
            exit;
        } else {
            echo "<script>alert('Error updating project: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        // Add a new project
        $insert_query = "INSERT INTO landing_project_links (title, video_link) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'ss', $title, $video_link);
        if (mysqli_stmt_execute($stmt)) {
            header('Location: landing_projects.php?status=added');
            exit;
        } else {
            echo "<script>alert('Error adding project: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Delete video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $delete_query = "DELETE FROM landing_project_links WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, 'i', $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        header('Location: landing_projects.php?status=deleted');
        exit;
    } else {
        echo "<script>alert('Error deleting project: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page Projects</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Landing Page Projects";
        include('./navbar.php');

        // Show SweetAlert notifications based on query string
        if (isset($_GET['status'])) {
            $status = $_GET['status'];
            echo "<script>";
            if ($status === 'added') {
                echo "Swal.fire('Added!', 'Project has been added successfully.', 'success');";
            } elseif ($status === 'updated') {
                echo "Swal.fire('Updated!', 'Project has been updated successfully.', 'success');";
            } elseif ($status === 'deleted') {
                echo "Swal.fire('Deleted!', 'Project has been deleted.', 'success');";
            } elseif ($status === 'error') {
                echo "Swal.fire('Error!', 'No Project were selected for deletion.', 'error');";
            }
            echo "</script>";
        }
        ?>
        <div class="p-6">
            <!-- Add Project Button -->
            <div>
                <button onclick="openAddEditPopup()" class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-cyan-600 rounded-md mb-6 transition-all">Add Project</button>
            </div>

            <!-- Project Table -->
            <form id="project-table-form" method="POST">
                <input type="hidden" name="delete_selected" value="1">
                <table id="project-table" class="min-w-full bg-white shadow-md rounded-md">
                    <thead>
                        <tr class="bg-gray-700 text-white">
                            <th class="border px-4 py-2 rounded-tl-md">No</th>
                            <th class="border px-4 py-2">Project Title</th>
                            <th class="border px-4 py-2">Link</th>
                            <th class="border px-4 py-2 rounded-tr-md">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM landing_project_links";
                        $result = mysqli_query($conn, $query);

                        if (mysqli_num_rows($result) > 0) {
                            $counter = 1;
                            while ($row = mysqli_fetch_assoc($result)) {
                                $title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                                $video_link = htmlspecialchars($row['video_link'], ENT_QUOTES, 'UTF-8');
                                echo "<tr>
                                    <td class='border px-4 py-2 text-center'>{$counter}</td>
                                    <td class='border px-4 py-2'>{$title}</td>
                                    <td class='border px-4 py-2 text-center'>";
                                if (!empty($row['video_link'])) {
                                    echo "<a href='{$video_link}' target='_blank' class='linkedin-btn inline-block px-4 py-1 bg-transparent text-red-600 border border-red-600 rounded-full transition hover:bg-red-600 hover:text-white'>Youtube</a>";
                                }
                                echo "</td>
                                    <td class='border px-4 py-2 text-center'>
                                        <button type='button' onclick='openAddEditPopup({$row['id']}, \"{$title}\", \"{$video_link}\")' class='text-blue-500 mr-2'>Edit</button>
                                        <button type='button' onclick='confirmDelete({$row['id']})' class='text-red-500'>Delete</button>
                                    </td>
                                </tr>";
                                $counter++;
                            }
                        } else {
                            echo "<tr><td colspan='4' class='border px-4 py-2 text-center'>No projects found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </form>

            <!-- Delete Form -->
            <form id="delete-form" action="landing_projects.php" method="POST" style="display: none;">
                <input type="hidden" name="delete_id" id="delete_id">
            </form>
        </div>

        <!-- Add/Edit Popup -->
        <div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center">
            <div class="bg-white rounded-lg p-6 w-96">
                <h2 id="popup-title" class="text-xl font-bold mb-4">Add/Edit Project Video</h2>
                <form id="popup-form" action="landing_projects.php" method="POST">
                    <input type="hidden" name="project_id" id="project_id">
                    <div class="mb-4">
                        <label for="title" class="block text-sm font-medium mb-1">Title *</label>
                        <input type="text" id="title" name="title" class="border-2 rounded-md p-2 w-full" required>
                    </div>
                    <div class="mb-4">
                        <label for="video_link" class="block text-sm font-medium mb-1">Video URL *</label>
                        <input type="text" id="video_link" name="video_link" class="border-2 rounded-md p-2 w-full" required>
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
            function openAddEditPopup(id = null, title = '', videoLink = '') {
                document.getElementById('popup-title').innerText = id ? 'Edit Project Info' : 'Add Project Video';
                document.getElementById('project_id').value = id || '';
                document.getElementById('title').value = title || '';
                document.getElementById('video_link').value = videoLink || '';
                document.getElementById('popup-modal').classList.remove('hidden');
            }

            // Function to close the popup
            function closePopup() {
                document.getElementById('popup-modal').classList.add('hidden');
            }

            // Function to confirm deletion
            function confirmDelete(id) {
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
                        document.getElementById('delete_id').value = id;
                        document.getElementById('delete-form').submit();
                    }
                });
            }
        </script>
    </div>
</body>
</html>