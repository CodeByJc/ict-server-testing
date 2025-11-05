<?php
// manage_faculty.php
// Faculty management UI modeled after manage_class.php UI/behavior.
// - AJAX endpoints (POST actions): fetch_faculty, fetch_single_faculty, add_faculty, update_faculty, delete_faculty.
// - Popup form collects faculty_info fields + address fields (address table).
// - Uses prepared statements, safe handling, and returns JSON only for AJAX POST requests.

if (session_status() === PHP_SESSION_NONE) session_start();

include('../../api/db/db_connection.php'); // expects $conn (mysqli)
if (!isset($conn) || !$conn) {
    http_response_code(500);
    die('Database connection failed');
}

function fetch_all_stmt($stmt) {
    $rows = [];
    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res !== false) {
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        }
    } else {
        $meta = mysqli_stmt_result_metadata($stmt);
        if ($meta) {
            $fields = [];
            $row = [];
            while ($f = mysqli_fetch_field($meta)) $fields[] = &$row[$f->name];
            mysqli_free_result($meta);
            call_user_func_array([$stmt, 'bind_result'], $fields);
            while (mysqli_stmt_fetch($stmt)) {
                $rec = [];
                foreach ($row as $k => $v) $rec[$k] = $v;
                $rows[] = $rec;
            }
        }
    }
    return $rows;
}

// Handle AJAX POST actions (JSON responses)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    mysqli_report(MYSQLI_REPORT_OFF); // prevent fatal DB errors from breaking JSON

    // catch PHP warnings, notices, or exceptions as JSON output
    set_error_handler(function($errno, $errstr, $errfile, $errline){
        echo json_encode([
            'status' => 'error',
            'message' => "PHP Error: $errstr in $errfile:$errline"
        ]);
        exit;
    });

    set_exception_handler(function($e){
        echo json_encode([
            'status' => 'error',
            'message' => 'Exception: '.$e->getMessage()
        ]);
        exit;
    });

    $action = $_POST['action'];

    // FETCH ALL FACULTY (list)
    if ($action === 'fetch_faculty') {

    // optional filter: designation, search
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';

    $sql = "SELECT 
                f.id,
                COALESCE(f.first_name,'') AS first_name,
                COALESCE(f.last_name,'') AS last_name,
                COALESCE(f.user_login_id,'') AS username,
                COALESCE(f.gender,'') AS gender,
                COALESCE(f.designation,'') AS designation,
                COALESCE(f.cabin_number,'') AS cabin_number,
                COALESCE(f.faculty_full_name,'') AS faculty_full_name,
                COALESCE(f.address_info_id, NULL) AS address_info_id,
                COALESCE(a.address_line,'') AS address_line,
                COALESCE(a.country_name,'') AS country_name,
                COALESCE(a.city,'') AS city,
                COALESCE(a.pincode,'') AS pincode
            FROM faculty_info f
            LEFT JOIN addresses a ON f.address_info_id = a.id
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($designation !== '') {
        $sql .= " AND f.designation = ?";
        $params[] = $designation;
        $types .= 's';
    }

    if ($search !== '') {
    $sql .= " AND (
        f.first_name LIKE CONCAT('%', ?, '%')
        OR f.last_name LIKE CONCAT('%', ?, '%')
        OR f.user_login_id LIKE CONCAT('%', ?, '%')
    )";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}


    $sql .= " ORDER BY f.first_name, f.last_name";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { echo json_encode(['status'=>'error','message'=>'DB prepare failed: '.mysqli_error($conn)]); exit; }

    if (!empty($params)) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'param' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    mysqli_stmt_execute($stmt);
    $rows = fetch_all_stmt($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['status'=>'success','faculty'=>$rows]);
    exit;
}

    // FETCH SINGLE FACULTY (for edit)
    if ($action === 'fetch_single_faculty') {
      $id = intval($_POST['id'] ?? 0);
      if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid id']); exit; }

      $sql = "SELECT 
                  f.id, 
                  f.first_name, 
                  f.last_name, 
                  f.user_login_id AS username,   -- ✅ alias for frontend
                  f.gender, 
                  f.designation, 
                  f.cabin_number, 
                  f.faculty_full_name, 
                  ul.phone_no AS faculty_contact_number,  -- ✅ alias for frontend consistency
                  f.address_info_id,
                  a.address_line, 
                  a.country_name, 
                  a.city, 
                  a.pincode
              FROM faculty_info f
              LEFT JOIN addresses a ON f.address_info_id = a.id
              LEFT JOIN user_login ul ON f.user_login_id = ul.username
              WHERE f.id = ? LIMIT 1";

      $stmt = mysqli_prepare($conn, $sql);
      if (!$stmt) { 
          echo json_encode(['status'=>'error','message'=>'DB prepare failed']); 
          exit; 
      }
      mysqli_stmt_bind_param($stmt, 'i', $id);
      mysqli_stmt_execute($stmt);
      $rows = fetch_all_stmt($stmt);
      mysqli_stmt_close($stmt);

      if (empty($rows)) {
          echo json_encode(['status'=>'error','message'=>'Faculty not found']);
      } else {
          echo json_encode(['status'=>'success','faculty'=>$rows[0]]);
      }
      exit;
 }


    // ADD FACULTY
    if ($action === 'add_faculty') {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $contact_number = trim($_POST['faculty_contact_number'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $cabin = trim($_POST['cabin_number'] ?? '');
    $full_name = trim($_POST['faculty_full_name'] ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $country_name = trim($_POST['country_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $faculty_personal_email = trim($_POST['faculty_personal_email'] ?? '');
    $faculty_mu_email = trim($_POST['faculty_mu_email'] ?? '');

    if ($first === '') {
        echo json_encode(['status' => 'error', 'message' => 'First name required']);
        exit;
    }

    mysqli_begin_transaction($conn);
    $address_id = null;

    // --- 1️⃣ Create user first if not exists ---
    if ($username !== '') {
        $chk = mysqli_prepare($conn, "SELECT username FROM user_login WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 's', $username);
        mysqli_stmt_execute($chk);
        $res = mysqli_stmt_get_result($chk);
        $exists = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($chk);

        if (!$exists) {
            $default_password = password_hash($first . '@' . $username, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn, 
                "INSERT INTO user_login (username, password, role, isactive, email, phone_no, device_token) 
                 VALUES (?, ?, 'faculty', 1, ?, ?, '')");
            mysqli_stmt_bind_param($ins, 'ssss', $username, $default_password, $faculty_mu_email, $contact_number);
            if (!mysqli_stmt_execute($ins)) {
                $err = mysqli_stmt_error($ins);
                mysqli_stmt_close($ins);
                mysqli_rollback($conn);
                echo json_encode(['status' => 'error', 'message' => 'User creation failed: ' . $err]);
                exit;
            }
            mysqli_stmt_close($ins);
        }
    }

    // --- 2️⃣ Insert address (now FK will work) ---
    if ($address_line || $country_name || $city || $pincode) {
        $sql = "INSERT INTO addresses (username, address_line, country_name, city, pincode)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            mysqli_rollback($conn);
            echo json_encode(['status'=>'error','message'=>'DB prepare failed (addresses insert)']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'sssss', $username, $address_line, $country_name, $city, $pincode);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            mysqli_rollback($conn);
            echo json_encode(['status'=>'error','message'=>'Address insert failed: '.$err]);
            exit;
        }

        $address_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    // --- 3️⃣ Insert faculty record ---
    if (!is_null($address_id)) {
        $sql = "INSERT INTO faculty_info 
                (first_name, last_name, user_login_id, gender, designation, cabin_number, faculty_full_name, address_info_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssssi', 
            $first, $last, $username, $gender, $designation, $cabin, $full_name, $address_id);
    } else {
        $sql = "INSERT INTO faculty_info 
                (first_name, last_name, user_login_id, gender, designation, cabin_number, faculty_full_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssss', 
            $first, $last, $username, $gender, $designation, $cabin, $full_name);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        echo json_encode(['status'=>'error','message'=>'Faculty insert failed: '.$err]); 
        exit;
    }

    mysqli_stmt_close($stmt);
    mysqli_commit($conn);
    echo json_encode(['status'=>'success','message'=>'Faculty added successfully']);
    exit;
}


    // UPDATE FACULTY
    
    // UPDATE FACULTY
    if ($action === 'update_faculty') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid ID']); exit; }

    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $cabin = trim($_POST['cabin_number'] ?? '');
    $full_name = trim($_POST['faculty_full_name'] ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $country_name = trim($_POST['country_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $contact_number = trim($_POST['faculty_contact_number'] ?? '');
    $faculty_personal_email = trim($_POST['faculty_personal_email'] ?? '');
    $faculty_mu_email = trim($_POST['faculty_mu_email'] ?? '');

    // --- Fetch existing address info --- //
    $stmt = mysqli_prepare($conn, "SELECT address_info_id FROM faculty_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $rows = fetch_all_stmt($stmt);
    mysqli_stmt_close($stmt);
    $existing_address_id = $rows[0]['address_info_id'] ?? null;
    $address_id = $existing_address_id;

    // --- Address handling --- //
    if ($address_line || $country_name || $city || $pincode) {
        if ($existing_address_id) {
            $sql = "UPDATE addresses 
                    SET username=?, address_line=?, country_name=?, city=?, pincode=? 
                    WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssi', 
                $username, $address_line, $country_name, $city, $pincode, $existing_address_id);
            if (!mysqli_stmt_execute($stmt)) {
                echo json_encode(['status'=>'error','message'=>'Address update failed: '.mysqli_stmt_error($stmt)]);
                exit;
            }
            mysqli_stmt_close($stmt);
        } else {
            // Insert new address
            $sql = "INSERT INTO addresses (username, address_line, country_name, city, pincode)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssss', 
                $username, $address_line, $country_name, $city, $pincode);
            if (!mysqli_stmt_execute($stmt)) {
                echo json_encode(['status'=>'error','message'=>'Address insert failed: '.mysqli_stmt_error($stmt)]);
                exit;
            }
            $address_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Update faculty_info with new address id
            $sql = "UPDATE faculty_info SET address_info_id=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ii', $address_id, $id);
            if (!mysqli_stmt_execute($stmt)) {
                echo json_encode(['status'=>'error','message'=>'Faculty update with new address failed: '.mysqli_stmt_error($stmt)]);
                exit;
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (!empty($_POST['clear_address'])) {
        if ($existing_address_id) {
            $stmt = mysqli_prepare($conn, "DELETE FROM addresses WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $existing_address_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $address_id = null;
    }

    // --- Update faculty_info --- //
    if ($address_id === null) {
        $sql = "UPDATE faculty_info 
                SET first_name=?, last_name=?, user_login_id=?, 
                    gender=?, designation=?, cabin_number=?, faculty_full_name=?, 
                    address_info_id=NULL 
                WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssssi', 
            $first, $last, $username, $gender, $designation, $cabin, $full_name, $id);
    } else {
        $sql = "UPDATE faculty_info 
                SET first_name=?, last_name=?, user_login_id=?, 
                    gender=?, designation=?, cabin_number=?, faculty_full_name=?, 
                    address_info_id=? 
                WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssssii', 
            $first, $last, $username, $gender, $designation, $cabin, $full_name, $address_id, $id);
    }

    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['status'=>'error','message'=>'Faculty update failed: '.mysqli_stmt_error($stmt)]);
        exit;
    }
    mysqli_stmt_close($stmt);

    // --- Sync user_login --- //
    if ($username !== '') {
        $check = mysqli_prepare($conn, "SELECT username FROM user_login WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $exists = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($check);

        if ($exists) {
            $upd = mysqli_prepare($conn, "UPDATE user_login SET email=?, phone_no=? WHERE username=?");
            mysqli_stmt_bind_param($upd, 'sss', $faculty_mu_email, $contact_number, $username);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }

    echo json_encode(['status'=>'success','message'=>'Faculty updated successfully']);
    exit;
}





// DELETE FACULTY
if ($action === 'delete_faculty') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid id']); exit; }

    $stmt = mysqli_prepare($conn, "SELECT address_info_id FROM faculty_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $rows = fetch_all_stmt($stmt);
    mysqli_stmt_close($stmt);
    $addr_id = $rows[0]['address_info_id'] ?? null;

    $stmt = mysqli_prepare($conn, "DELETE FROM faculty_info WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['status'=>'error','message'=>'Faculty delete failed: '.mysqli_stmt_error($stmt)]); exit;
    }
    mysqli_stmt_close($stmt);

    if ($addr_id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM addresses WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $addr_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['status'=>'success','message'=>'Faculty removed']);
    exit;
  }
    

    echo json_encode(['status'=>'error','message'=>'Unknown action']);
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Faculty</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<style>
  .tab-button-active { border-bottom-color: #06b6d4; color: #06b6d4; }
  .tab-button-inactive { border-bottom-color: transparent; color: #4b5563; }
</style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
<?php $page_title = "Manage Faculty"; include('./navbar.php'); ?>

<div class="p-5">
  <div class="bg-white shadow-xl rounded-xl p-3 mb-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
    <div>
      <label class="block text-sm font-medium">Designation</label>
      <select id="designation-filter" class="mt-1 w-full p-2 border rounded">
        <option value="">All Designations</option>
        <option value="hod">HOD</option>
        <option value="ap">AP</option>
        <option value="tp">TP</option>
        <option value="la">LA</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium">Search</label>
      <input id="search-filter" type="text" class="mt-1 w-full p-2 border rounded" placeholder="Name / Emp code / Username">
    </div>

    <div>
      <label class="block text-sm font-medium">&nbsp;</label>
      <div class="flex gap-2">
        <button id="refresh-faculty" class="bg-cyan-500 text-white px-4 py-2 rounded">Refresh</button>
        <button id="open-create" class="bg-green-500 text-white px-4 py-2 rounded">Add Faculty</button>
      </div>
    </div>

    <div class="flex items-center">
      <p class="text-sm text-gray-500">Click a faculty to view/edit details in the popup.</p>
    </div>
  </div>

  <div id="faculty-container" class="bg-white shadow-xl rounded-xl p-3">
    <div id="faculty-tabs" class="min-h-40">
      <p class="text-sm text-gray-500">No faculty loaded. Click Refresh or Add Faculty.</p>
    </div>
  </div>
</div>
</div><!-- main-content -->

<!-- Add/Edit popup -->
<!-- Faculty Create/Edit Popup -->
<div id="create-faculty-popup" 
     class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
  <div class="bg-white p-6 rounded-lg w-5/12 max-w-10xl overflow-auto max-h-[90vh]">
    <h3 id="popup-title" class="text-xl font-bold m-4">Add Faculty</h3>

    <form id="create-faculty-form" class="space-y-4">
      <input type="hidden" name="id" id="fac-id">

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block font-semibold">First Name</label>
          <input name="first_name" id="fac-first" class="w-full p-2 border rounded" required>
        </div>
        <div>
          <label class="block font-semibold">Last Name</label>
          <input name="last_name" id="fac-last" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Emp Code (Username)</label>
          <input name="username" id="fac-username" class="w-full p-2 border rounded" required>
        </div>
        <div>
          <label class="block font-semibold">Full Name</label>
          <input name="faculty_full_name" id="fac-fullname" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Personal Email</label>
          <input name="faculty_personal_email" id="fac-personal-email" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">MU Email</label>
          <input name="faculty_mu_email" id="fac-mu-email" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Contact Number</label>
          <input name="faculty_contact_number" id="fac-contact" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Designation</label>
          <select name="designation" id="fac-designation" class="w-full p-2 border rounded">
            <option value="">Select</option>
            <option value="hod">HOD</option>
            <option value="AP">AP</option>
            <option value="TP">TP</option>
            <option value="LA">LA</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold">Gender</label>
          <select name="gender" id="fac-gender" class="w-full p-2 border rounded">
            <option value="">Select</option>
            <option value="MALE">Male</option>
            <option value="FEMALE">Female</option>
            <option value="OTHER">Other</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold">Cabin Number</label>
          <input name="cabin_number" id="fac-cabin" class="w-full p-2 border rounded">
        </div>
      </div>

      <hr>

      <h4 class="font-semibold">Address</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold">Address Line</label>
          <input name="address_line" id="fac-address-line" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Country</label>
          <input name="country_name" id="fac-country" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">City</label>
          <input name="city" id="fac-city" class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block font-semibold">Pincode</label>
          <input name="pincode" id="fac-pincode" class="w-full p-2 border rounded">
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t">
        <button type="button" id="popup-cancel" class="px-4 py-2 rounded bg-gray-500 text-white">Cancel</button>
        <button type="submit" id="popup-submit" class="px-4 py-2 rounded bg-cyan-500 text-white">Save</button>
      </div>
    </form>
  </div>
</div>



<script>
$(function(){
  function escapeHtml(text){ if (text===undefined||text===null) return ''; return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

  // Initial load
  loadFacultyTabs();

  $('#refresh-faculty').on('click', function(){
    loadFacultyTabs();
  });

  $('#open-create').on('click', function(){
    openFacultyPopup(null);
  });

  // Filters
  $('#designation-filter, #search-filter').on('change keyup', function(){
    loadFacultyTabs();
  });

  // Load faculty and render as tabs/cards similar to manage_class
  function loadFacultyTabs(){
  const designation = $('#designation-filter').val() || '';
  const search = $('#search-filter').val() || '';
  $('#faculty-tabs').html('<p class="text-sm text-gray-500">Loading...</p>');
  $.ajax({
    url: '',
    method: 'POST',
    dataType: 'json',
    data: { action: 'fetch_faculty', designation: designation, search: search },
    success: function(resp){
      if (!resp || resp.status !== 'success' || !Array.isArray(resp.faculty)) {
        $('#faculty-tabs').html('<p class="text-sm text-gray-500">Error loading faculty.</p>');
        return;
      }
      const list = resp.faculty;
      if (list.length === 0) {
        $('#faculty-tabs').html('<p class="text-sm text-gray-500">No faculty found. Click Add Faculty to create one.</p>');
        return;
      }

      // build tabs
      let tabsHtml = '<div class="flex flex-wrap gap-2 border-b pb-3">';
      list.forEach((f, i) => {
        const labelText = (f.faculty_full_name && f.faculty_full_name.trim() !== '') ? f.faculty_full_name : ((f.first_name||'') + ' ' + (f.last_name||''));
        const label = escapeHtml(labelText.trim());
        tabsHtml += `<button type="button" class="fac-tab px-4 py-2 -mb-px border-b-2 ${i===0 ? 'tab-button-active' : 'tab-button-inactive'}" data-id="${escapeHtml(String(f.id))}">${label}</button>`;
      });
      tabsHtml += '</div>';

      // build content panes
      let content = '<div class="mt-4">';
      list.forEach((f, i) => {
        const id = escapeHtml(String(f.id));
        const displayName = (f.faculty_full_name && f.faculty_full_name.trim() !== '') ? f.faculty_full_name : ((f.first_name||'') + ' ' + (f.last_name||''));
        const safeDisplayName = escapeHtml(displayName.trim());
        // contact: username, emails, phone
        const username = f.username || f.user_login_id || '';
        const personalEmail = f.faculty_personal_email || f.personal_email || '';
        const muEmail = f.faculty_mu_email || f.mu_email || '';
        const phone = f.faculty_contact_number || f.contact_number || f.phone_no || '';
        let contactHtml = '';
        if (f.profile_pic) {
          contactHtml = `<img src="${escapeHtml(f.profile_pic)}" class="inline-block rounded-full w-8 h-8 mr-2 align-middle"> ${escapeHtml(username)}`;
        } else {
          contactHtml = escapeHtml(username || '');
        }
        const extras = [];
        if (personalEmail) extras.push(escapeHtml(personalEmail));
        if (muEmail) extras.push(escapeHtml(muEmail));
        if (phone) extras.push(escapeHtml(phone));
        const contactExtra = extras.length ? ('<div class="text-sm text-gray-600 mt-1">' + extras.join(' • ') + '</div>') : '';

        const addressParts = [f.address_line, f.city, f.country_name, f.pincode].filter(Boolean);
        const safeAddress = escapeHtml(addressParts.join(', '));

        content += `
          <div id="pane-${id}" class="pane ${i===0 ? '' : 'hidden'} p-4 bg-gray-50 rounded-b-xl">
            <div class="flex justify-between items-start gap-4">
              <div>
                <h4 class="font-semibold text-gray-700">${safeDisplayName}</h4>
                <div class="text-sm text-gray-600">${escapeHtml(f.designation || '')}${f.cabin_number ? ' • Cabin: ' + escapeHtml(f.cabin_number) : ''}</div>
                <div class="text-sm text-gray-600 mt-2">${contactHtml}${contactExtra}</div>
                <div class="text-sm text-gray-600 mt-1">Address: ${safeAddress}</div>
              </div>
              <div class="flex gap-2">
                <button type="button" class="edit-fac-btn px-4 py-1 rounded border border-blue-600 text-blue-600" data-id="${id}">Edit</button>
                <button type="button" class="delete-fac-btn px-4 py-1 rounded border border-red-600 text-red-600" data-id="${id}">Delete</button>
              </div>
            </div>
          </div>
        `;
      });
      content += '</div>';

      $('#faculty-tabs').html(tabsHtml + content);

      // tab switching (delegated already but we rebind to ensure styles)
      $('.fac-tab').off('click').on('click', function(){
        $('.fac-tab').removeClass('tab-button-active').addClass('tab-button-inactive');
        $(this).removeClass('tab-button-inactive').addClass('tab-button-active');
        const id = $(this).data('id');
        $('[id^="pane-"]').addClass('hidden');
        $(`#pane-${id}`).removeClass('hidden');
      });
    },
    error: function(){
      $('#faculty-tabs').html('<p class="text-sm text-red-500">Failed to load faculty.</p>');
    }
  });
}

  // Open popup for add/edit
  function openFacultyPopup(data){
    $('#fac-id').val(data?.id || '');
    $('#fac-first').val(data?.first_name || '');
    $('#fac-last').val(data?.last_name || '');
    $('#fac-personal-email').val(data?.faculty_personal_email || '');
    $('#fac-mu-email').val(data?.faculty_mu_email || '');
    $('#fac-contact').val(data?.faculty_contact_number || data?.phone_no || '');
    $('#fac-designation').val(data?.designation || 'AP');
    $('#fac-gender').val(data?.gender || 'MALE');
    $('#fac-cabin').val(data?.cabin_number || '');
    $('#fac-fullname').val(data?.faculty_full_name || '');
    $('#fac-address-line').val(data?.address_line || '');
    $('#fac-country').val(data?.country_name || '');
    $('#fac-username').val(data?.user_login_id || data?.username || '');
    $('#fac-city').val(data?.city || '');
    $('#fac-pincode').val(data?.pincode || '');
    $('#popup-title').text(data ? 'Edit Faculty' : 'Add Faculty');
    $('#create-faculty-popup').removeClass('hidden');
    const gender = (data?.gender || '').trim().toUpperCase();
    const designation = (data?.designation || '').trim();

    $('#fac-gender').val(
      ['MALE', 'FEMALE', 'OTHER'].includes(gender) ? gender : ''
    );

    // Example if your dropdown has values like: 'AP', 'HOD', 'Professor', etc.
    $('#fac-designation').val(designation || '');

    $('#create-faculty-popup').removeClass('hidden');
  }

  // Edit button click
  $(document).on('click', '.edit-fac-btn', function(){
    const id = $(this).data('id');
    $.ajax({
      url: '',
      method: 'POST',
      dataType: 'json',
      data: { action: 'fetch_single_faculty', id: id },
      success: function(resp){
        if (!resp || resp.status !== 'success') return Swal.fire('Error','Failed to fetch faculty','error');
        openFacultyPopup(resp.faculty);
      },
      error: function(){ Swal.fire('Error','Failed to fetch faculty','error'); }
    });
  });

  // Delete button click
  $(document).on('click', '.delete-fac-btn', function(){
    const id = $(this).data('id');
    Swal.fire({
      title: 'Are you sure?',
      text: 'This will permanently delete the faculty record.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Delete'
    }).then((r) => {
      if (!r.isConfirmed) return;
      $.ajax({
        url: '',
        method: 'POST',
        dataType: 'json',
        data: { action: 'delete_faculty', id: id },
        success: function(resp){
          if (resp && resp.status === 'success') {
            Swal.fire('Deleted', resp.message, 'success');
            loadFacultyTabs();
          } else {
            Swal.fire('Error', resp?.message || 'Failed to delete', 'error');
          }
        },
        error: function(){ Swal.fire('Error','Delete request failed','error'); }
      });
    });
  });

  // Popup cancel
  $('#popup-cancel').on('click', function(){
    $('#create-faculty-popup').addClass('hidden');
    $('#create-faculty-form')[0].reset();
  });

  // Popup save (add/update)
  $('#create-faculty-form').on('submit', function (e) {
  e.preventDefault();

  const id = $('#fac-id').val();
  const payload = $(this).serializeArray();
  payload.push({ name: 'action', value: id ? 'update_faculty' : 'add_faculty' });
  if (id) payload.push({ name: 'id', value: id });

  $.ajax({
    url: '', // same PHP file
    method: 'POST',
    dataType: 'json',
    data: payload,
    success: function (resp) {
      console.log('Server Response:', resp); // ✅ always log full response for debugging

      if (!resp) {
        console.error('Empty response from server');
        Swal.fire('Error', 'No response received', 'error');
        return;
      }

      if (resp.status === 'success') {
        Swal.fire('Success', resp.message || 'Saved', 'success');
        $('#create-faculty-popup').addClass('hidden');
        $('#create-faculty-form')[0].reset();
        loadFacultyTabs();
      } else {
        console.error('Backend Error:', resp.message || 'Unknown error'); // ✅ show error details
        Swal.fire('Error', resp.message || 'Failed to save', 'error');
      }
    },
    error: function (xhr, status, error) {
      console.error('AJAX Error:', error); // ✅ log AJAX failure details
      console.error('Status:', status);
      console.error('Response Text:', xhr.responseText);
      Swal.fire('Error', 'Request failed — see console for details', 'error');
    }
  });
});



});
</script>
</body>
</html>