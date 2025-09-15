<?php
include('../../api/db/db_connection.php');

// ---------- URL Validators ----------
function isValidWebsite($url) {
    return filter_var($url, FILTER_VALIDATE_URL) &&
           str_starts_with($url, "https://");
}

function isValidLinkedIn($url) {
    return filter_var($url, FILTER_VALIDATE_URL) &&
           str_starts_with($url, "https://") &&
           strpos($url, "linkedin.com") !== false;
}

// ---------- Handle Add/Edit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_name'], $_POST['company_type'], $_POST['company_domnain'], $_POST['company_website'], $_POST['company_linkedin'])) {
    $company_id      = isset($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $company_name    = mysqli_real_escape_string($conn, $_POST['company_name']);
    $company_type    = mysqli_real_escape_string($conn, $_POST['company_type']);
    $company_domain    = mysqli_real_escape_string($conn, $_POST['company_domain']);
    $company_website = mysqli_real_escape_string($conn, $_POST['company_website']);
    $company_linkedin= mysqli_real_escape_string($conn, $_POST['company_linkedin']);

    // ✅ Backend validation
    if (!isValidWebsite($company_website)) {
        header('Location: companies.php?status=invalid_website');
        exit;
    }
    if (!empty($company_linkedin) && !isValidLinkedIn($company_linkedin)) {
        header('Location: companies.php?status=invalid_linkedin');
        exit;
    }

    if ($company_id) {
        $update_query = "UPDATE company_info 
                         SET company_name = '$company_name', company_type = '$company_type', company_domain = '$company_domain', 
                             company_website = '$company_website', company_linkedin = '$company_linkedin' 
                         WHERE id = $company_id";
        if (mysqli_query($conn, $update_query)) {
            header('Location: companies.php?status=updated');
            exit;
        }
    } else {
        $insert_query = "INSERT INTO company_info (company_name, company_type, company_domain, company_website, company_linkedin) 
                         VALUES ('$company_name', '$company_type', '$company_domain', '$company_website', '$company_linkedin')";
        if (mysqli_query($conn, $insert_query)) {
            header('Location: companies.php?status=added');
            exit;
        }
    }
}
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
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
  <?php include('./sidebar.php'); ?>
  <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
    <?php
    $page_title = "Companies";
    include('./navbar.php');

    // SweetAlert notifications
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        echo "<script>";
        if ($status === 'added') {
            echo "Swal.fire('Added!', 'Company has been added successfully.', 'success');";
        } elseif ($status === 'updated') {
            echo "Swal.fire('Updated!', 'Company has been updated successfully.', 'success');";
        } elseif ($status === 'deleted') {
            echo "Swal.fire('Deleted!', 'Selected company has been deleted.', 'success');";
        } elseif ($status === 'error') {
            echo "Swal.fire('Error!', 'No company was selected for deletion.', 'error');";
        } elseif ($status === 'invalid_website') {
            echo "Swal.fire('Invalid Website!', 'Please enter a valid Website URL (must start with https://).', 'error');";
        } elseif ($status === 'invalid_linkedin') {
            echo "Swal.fire('Invalid LinkedIn!', 'Please enter a valid LinkedIn URL (must start with https:// and contain linkedin.com).', 'error');";
        }
        echo "</script>";
    }
    ?>

    <div class="p-6">
      <!-- Add button + search -->
      <div>
        <button onclick="openAddEditPopup()" 
          class="bg-cyan-500 shadow-md hover:shadow-xl px-6 text-white p-2 hover:bg-cyan-600 rounded-md mb-6 transition-all">
          Add Company
        </button>
        <input type="text" id="search" class="shadow-lg ml-5 pl-4 p-2 rounded-md w-1/2" 
          placeholder="Search Companies..." onkeyup="searchTable()">
      </div>

      <!-- Companies Table -->
      <table id="company-table" class="min-w-full bg-white shadow-md rounded-md">
        <thead>
          <tr class="bg-gray-700 text-white">
            <th class="border px-4 py-2 rounded-tl-md">No</th>
            <th class="border px-4 py-2">Company Name</th>
            <th class="border px-4 py-2">Type</th>
            <th class="border px-4 py-2">Domain</th>
            <th class="border px-4 py-2">Website</th>
            <th class="border px-4 py-2">LinkedIn</th>
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
                          <td class='border px-4 py-2 text-center'>{$row['company_domain']}</td>
                          <td class='border px-4 py-2 text-center'>";
                  // Website button
                  if (!empty($row['company_website']) && isValidWebsite($row['company_website'])) {
                      echo "<a href='{$row['company_website']}' target='_blank' 
                              class='inline-block px-4 py-1 bg-transparent text-orange-500 border border-orange-500 rounded-full transition hover:bg-orange-500 hover:text-white'>
                              Website
                            </a>";
                  } else {
                      echo "<div class='inline-block px-4 py-1 text-gray-400 border border-gray-300 rounded-full opacity-50 cursor-not-allowed'>
                              Website
                            </div>";
                  }
                  echo "</td>
                        <td class='border px-4 py-2 text-center'>";
                  // LinkedIn button
                  if (!empty($row['company_linkedin']) && isValidLinkedIn($row['company_linkedin'])) {
                      echo "<a href='{$row['company_linkedin']}' target='_blank' 
                              class='inline-block px-4 py-1 bg-transparent text-blue-600 border border-blue-600 rounded-full transition hover:bg-blue-600 hover:text-white'>
                              LinkedIn
                            </a>";
                  } else {
                      echo "<div class='inline-block px-4 py-1 text-gray-400 border border-gray-300 rounded-full opacity-50 cursor-not-allowed'>
                              LinkedIn
                            </div>";
                  }
                  echo "</td>
                        <td class='border px-4 py-2 text-center'>
                          <button type='button' onclick='openAddEditPopup({$row['id']}, \"{$row['company_name']}\", \"{$row['company_type']}\", \"{$row['company_website']}\", \"{$row['company_linkedin']}\")' 
                            class='text-blue-500 mr-2'>Edit</button>
                        </td>
                      </tr>";
                  $counter++;
              }
          } else {
              echo "<tr><td colspan='6' class='border px-4 py-2 text-center'>No companies found</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>

    <!-- Popup Modal -->
    <div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center">
      <div class="bg-white rounded-lg p-6 w-96">
        <h2 id="popup-title" class="text-xl font-bold mb-4">Add/Edit Company</h2>
        <form id="popup-form" action="companies.php" method="POST" onsubmit="return validateForm()">
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
            <label for="company_domain" class="block text-sm font-medium mb-1">Company Domain *</label>
            <input type="text" id="company_domain" name="company_domain" class="border-2 rounded-md p-2 w-full" required>
          </div>
          <div class="mb-4">
            <label for="company_website" class="block text-sm font-medium mb-1">Company Website URL *</label>
            <input type="text" id="company_website" name="company_website" class="border-2 rounded-md p-2 w-full" required>
          </div>
          <div class="mb-4">
            <label for="company_linkedin" class="block text-sm font-medium mb-1">Company LinkedIn URL</label>
            <input type="text" id="company_linkedin" name="company_linkedin" placeholder="(Optional)" class="border-2 rounded-md p-2 w-full">
          </div>
          <div class="flex justify-end gap-4">
            <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 hover:bg-gray-600 text-white p-2 rounded-full">Cancel</button>
            <button type="submit" id="popup-submit" class="pl-6 pr-6 bg-cyan-500 hover:bg-cyan-600 text-white p-2 rounded-full">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Open Add/Edit Popup
    function openAddEditPopup(id = null, name = '', type = '', website = '', linkedin = '') {
      document.getElementById('popup-title').innerText = id ? 'Edit Company' : 'Add Company';
      document.getElementById('company_id').value = id || '';
      document.getElementById('company_name').value = name || '';
      document.getElementById('company_type').value = type || 'Gujarat Based';
      document.getElementById('company_domain').value = '';
      document.getElementById('company_website').value = website || '';
      document.getElementById('company_linkedin').value = linkedin || '';
      document.getElementById('popup-modal').classList.remove('hidden');
    }

    function closePopup() {
      document.getElementById('popup-modal').classList.add('hidden');
    }

    // ---------- Frontend Validation ----------
    function validateForm() {
      let website = document.getElementById('company_website').value.trim();
      let linkedin = document.getElementById('company_linkedin').value.trim();

      // Website must start with https://
      if (!/^https:\/\/[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(\/.*)?$/.test(website)) {
        alert("Please enter a valid Website URL (must start with https://).");
        return false;
      }

      // LinkedIn (optional) must be valid and contain linkedin.com
      if (linkedin && !/^https:\/\/(www\.)?linkedin\.com\/.*$/.test(linkedin)) {
        alert("Please enter a valid LinkedIn URL (must start with https:// and contain linkedin.com).");
        return false;
      }

      return true;
    }

    // Search
    function searchTable() {
      const searchInput = document.getElementById('search').value.toLowerCase();
      const rows = document.querySelectorAll('#company-table tbody tr');
      rows.forEach(row => {
        const companyName = row.cells[1].textContent.toLowerCase();
        const companyType = row.cells[2].textContent.toLowerCase();
        row.style.display = (companyName.includes(searchInput) || companyType.includes(searchInput)) ? '' : 'none';
      });
    }
  </script>
</body>
</html>
