<?php
// subject_allocation.php
// Manage faculty <-> elective subject allocations.
// - Select semester -> loads elective subjects for that semester
// - Select subject -> list allocated faculties
// - Allocate faculty to subject (no class required) and delete allocations
// Uses ../../api/db/db_connection.php which must set $conn (mysqli)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');
if (!$conn) {
    http_response_code(500);
    die('Database connection failed');
}

// Portable helper for fetch_all from prepared stmt
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
            while ($f = mysqli_fetch_field($meta)) {
                $fields[] = &$row[$f->name];
            }
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

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Accept action from form POST or JSON body (defensive)
    $action = $_POST['action'] ?? null;
    if (!$action) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['action'])) {
                $action = $decoded['action'];
                $_POST = array_merge($_POST, $decoded);
            }
        }
    }

    if (!$action) {
        echo json_encode(['status' => 'error', 'message' => 'No action specified']);
        exit;
    }

    // normalize
    $action = trim($action);

    // 1) fetch subjects for semester
    if ($action === 'fetch_subjects_by_sem' || $action === 'fetch_subjects') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester id']);
            exit;
        }

        $sql = "SELECT id, subject_name FROM subject_info WHERE sem_info_id = ? AND `type` = 'elective' ORDER BY subject_name";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'DB prepare failed: ' . mysqli_error($conn)]);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $subjects = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'subjects' => $subjects]);
        exit;
    }

    // 2) fetch allocations (faculties) for a subject
    if ($action === 'fetch') {
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid subject id']);
            exit;
        }

        $query = "SELECT sa.id AS allocation_id, fi.id AS faculty_id, fi.first_name, fi.last_name, COALESCE(ul.email, '') AS email
                  FROM subject_allocation sa
                  JOIN faculty_info fi ON sa.faculty_info_id = fi.id
                  LEFT JOIN user_login ul ON fi.user_login_id = ul.username
                  WHERE sa.subject_info_id = ?
                  ORDER BY fi.first_name, fi.last_name";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) { echo json_encode(['status'=>'error','message'=>'DB prepare failed: '.mysqli_error($conn)]); exit; }
        mysqli_stmt_bind_param($stmt, 'i', $subject_id);
        mysqli_stmt_execute($stmt);
        $rows = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'data' => $rows]);
        exit;
    }

    // 3) allocate faculty to subject
    if ($action === 'allocate') {
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        $faculty_id = isset($_POST['faculty_info_id']) ? intval($_POST['faculty_info_id']) : 0;
        if ($subject_id <= 0 || $faculty_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid subject or faculty id']);
            exit;
        }

        // check duplicate
        $check = "SELECT id FROM subject_allocation WHERE subject_info_id = ? AND faculty_info_id = ? LIMIT 1";
        $cstmt = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($cstmt, 'ii', $subject_id, $faculty_id);
        mysqli_stmt_execute($cstmt);
        mysqli_stmt_store_result($cstmt);
        if (mysqli_stmt_num_rows($cstmt) > 0) {
            mysqli_stmt_close($cstmt);
            echo json_encode(['status' => 'error', 'message' => 'Faculty already allocated to this subject']);
            exit;
        }
        mysqli_stmt_close($cstmt);

        $ins = "INSERT INTO subject_allocation (subject_info_id, faculty_info_id, class_info_id) VALUES (?, ?, null)";
        $istmt = mysqli_prepare($conn, $ins);
        if (!$istmt) { echo json_encode(['status'=>'error','message'=>'DB prepare failed: '.mysqli_error($conn)]); exit; }
        mysqli_stmt_bind_param($istmt, 'ii', $subject_id, $faculty_id);
        if (mysqli_stmt_execute($istmt)) {
            mysqli_stmt_close($istmt);
            echo json_encode(['status' => 'success']);
        } else {
            $err = mysqli_stmt_error($istmt);
            mysqli_stmt_close($istmt);
            echo json_encode(['status' => 'error', 'message' => 'Failed to allocate: ' . $err]);
        }
        exit;
    }

    // 4) delete allocation
    if ($action === 'delete') {
        $allocation_id = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        if ($allocation_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid allocation id']);
            exit;
        }

        $del = "DELETE FROM subject_allocation WHERE id = ?";
        $dstmt = mysqli_prepare($conn, $del);
        if (!$dstmt) { echo json_encode(['status'=>'error','message'=>'DB prepare failed: '.mysqli_error($conn)]); exit; }
        mysqli_stmt_bind_param($dstmt, 'i', $allocation_id);
        if (mysqli_stmt_execute($dstmt)) {
            mysqli_stmt_close($dstmt);
            echo json_encode(['status' => 'success']);
        } else {
            $err = mysqli_stmt_error($dstmt);
            mysqli_stmt_close($dstmt);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete: ' . $err]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Subject Allocation</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  #faculty-table th, #faculty-table td { text-align:center; border:1px solid #d1d5db; }
  #faculty-table th { background:#374151; color:#fff; }
  .placeholder { color: #6b7280; padding: 1rem 0; }
</style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
<?php $page_title = "Subject Allocation"; include('./navbar.php'); ?>
<div class="container mx-auto p-6">
  <div class="bg-white p-6 rounded-xl shadow-md mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block font-bold mb-2">Select Semester</label>
        <select id="sem_info" class="w-full p-3 border-2 rounded-xl">
          <option value="" disabled selected>Select Semester</option>
          <?php
          $sem_q = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
          $sem_res = mysqli_query($conn, $sem_q);
          while ($s = mysqli_fetch_assoc($sem_res)) {
              $label = "SEM {$s['sem']} - " . strtoupper($s['edu_type']);
              echo "<option value='{$s['id']}'>{$label}</option>";
          }
          ?>
        </select>
      </div>
      <div>
        <label class="block font-bold mb-2">Select Subject (Elective)</label>
        <select id="subject" class="w-full p-3 border-2 rounded-xl" disabled>
          <option value="" disabled selected>Select Subject</option>
        </select>
      </div>
    </div>
  </div>

  <div class="p-6 bg-white rounded-xl shadow-md">
    <button id="allocate-btn" onclick="openAllocatePopup()" class="bg-cyan-500 text-white px-6 py-2 rounded-full mb-6" disabled>Allocate Faculty</button>
    <table id="faculty-table" class="min-w-full bg-white shadow-lg rounded-md border border-gray-300">
      <thead>
        <tr class="bg-gray-700 text-white">
          <th class="px-4 py-2">No</th>
          <th class="px-4 py-2">Faculty Name</th>
          <th class="px-4 py-2">Email</th>
          <th class="px-4 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <!-- leave tbody empty for DataTables -->
      </tbody>
    </table>
  </div>
</div>

<!-- popup -->
<div id="allocate-popup" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-96">
    <h2 id="popup-title" class="text-xl font-bold mb-4">Allocate Faculty</h2>
    <form id="allocate-form">
      <input type="hidden" id="subject_info_id" name="subject_info_id">
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Select Faculty</label>
        <select id="faculty" name="faculty_info_id" class="border-2 rounded p-2 w-full" required>
          <option value="" disabled selected>Select Faculty</option>
          <?php
          $faculty_q = "SELECT id, first_name, last_name FROM faculty_info ORDER BY first_name, last_name";
          $faculty_r = mysqli_query($conn, $faculty_q);
          while ($f = mysqli_fetch_assoc($faculty_r)) {
              echo "<option value='{$f['id']}'>" . htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) . "</option>";
          }
          mysqli_free_result($faculty_r);
          ?>
        </select>
      </div>
      <div class="flex justify-end gap-4">
        <button type="button" onclick="closePopup()" class="bg-gray-500 text-white px-4 py-2 rounded-full">Cancel</button>
        <button type="submit" class="bg-cyan-500 text-white px-4 py-2 rounded-full">Allocate</button>
      </div>
    </form>
  </div>
</div>

</div><!-- main-content -->

<script>
$(function() {
  // Destroy any existing instance (defensive) then initialize once with explicit columns
  if ($.fn.DataTable.isDataTable('#faculty-table')) {
    $('#faculty-table').DataTable().destroy();
    $('#faculty-table tbody').empty();
  }

  const table = $('#faculty-table').DataTable({
    paging: false,
    info: false,
    searching: false,
    ordering: false,
    columns: [
      { data: 'no', title: 'No' },
      { data: 'faculty_name', title: 'Faculty Name' },
      { data: 'email', title: 'Email' },
      { data: 'actions', title: 'Actions', orderable: false, searchable: false }
    ],
    language: {
      emptyTable: 'Select a semester and subject to load allocated faculty'
    },
    // Prevent DataTables from trying to read initial DOM rows (we keep tbody empty)
    retrieve: false,
    deferRender: true
  });

  // Load subjects for selected semester
  $('#sem_info').on('change', function() {
    const semId = $(this).val();
    $('#subject').prop('disabled', true).empty().append('<option disabled selected>Loading...</option>');
    $('#allocate-btn').prop('disabled', true);
    table.clear().draw();

    if (!semId) return;

    $.post('', { action: 'fetch_subjects_by_sem', sem_info_id: semId }, function(resp) {
      if (!resp || resp.status !== 'success') {
        console.error('fetch_subjects_by_sem:', resp);
        Swal.fire('Error', resp?.message || 'Failed to load subjects', 'error');
        $('#subject').empty().append('<option disabled selected>Select Subject</option>').prop('disabled', true);
        return;
      }
      const subs = resp.subjects || [];
      $('#subject').empty().append('<option disabled selected>Select Subject</option>');
      subs.forEach(s => $('#subject').append(`<option value="${s.id}">${s.subject_name}</option>`));
      $('#subject').prop('disabled', subs.length === 0);
      if (subs.length === 0) Swal.fire('Info', 'No elective subjects found for this semester', 'info');
    }, 'json').fail(function(xhr) {
      console.error('fetch_subjects_by_sem error', xhr.responseText);
      Swal.fire('Error', 'Failed to load subjects. See console.', 'error');
      $('#subject').prop('disabled', true);
    });
  });

  // When subject changes, load allocations and add rows with exact properties matching columns.data
  $('#subject').on('change', function() {
    const subjectId = $(this).val();
    $('#allocate-btn').prop('disabled', !subjectId);
    table.clear().draw();
    if (!subjectId) return;

    $.post('', { action: 'fetch', subject_info_id: subjectId }, function(resp) {
      if (!resp || resp.status !== 'success' || !Array.isArray(resp.data)) {
        console.error('Invalid fetch response', resp);
        Swal.fire('Error', resp?.message || 'Failed to fetch allocations', 'error');
        return;
      }

      const rows = resp.data.map((r, idx) => ({
        no: idx + 1,
        faculty_name: `${r.first_name || ''} ${r.last_name || ''}`.trim(),
        email: r.email || '',
        actions: `<button class="text-red-500" onclick="deleteAllocation(${r.allocation_id})">Delete</button>`
      }));

      // rows must be objects matching 'columns.data' names
      table.clear().rows.add(rows).draw();
    }, 'json').fail(function(xhr){
      console.error('fetch error', xhr.responseText);
      Swal.fire('Error', 'Failed to load allocations', 'error');
    });
  });

  // Allocation form submit
  $('#allocate-form').on('submit', function(e) {
    e.preventDefault();
    const subjectId = $('#subject_info_id').val();
    const facultyId = $('#faculty').val();
    if (!subjectId || !facultyId) { Swal.fire('Error','Select subject and faculty','error'); return; }

    $.post('', { action: 'allocate', subject_info_id: subjectId, faculty_info_id: facultyId }, function(resp) {
      if (resp && resp.status === 'success') {
        Swal.fire('Allocated','Faculty allocated successfully','success').then(()=> {
          closePopup();
          $('#subject').trigger('change');
        });
      } else {
        console.error('allocate resp', resp);
        Swal.fire('Error', resp?.message || 'Failed to allocate', 'error');
      }
    }, 'json').fail(function(xhr){
      console.error('allocate error', xhr.responseText);
      Swal.fire('Error','Failed to allocate','error');
    });
  });
});

// popup helpers
function openAllocatePopup() {
  const subjectText = $('#subject option:selected').text();
  const subjectId = $('#subject').val();
  if (!subjectId) { Swal.fire('Error','Select subject first','error'); return; }
  $('#popup-title').text(`Allocate Faculty for ${subjectText}`);
  $('#subject_info_id').val(subjectId);
  $('#allocate-popup').removeClass('hidden');
}

function closePopup() {
  $('#allocate-popup').addClass('hidden');
  $('#allocate-form')[0].reset();
  $('#subject_info_id').val('');
}

function deleteAllocation(allocation_id) {
  if (!allocation_id) return;
  Swal.fire({
    title: 'Confirm delete',
    text: 'This will remove the allocation permanently.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete'
  }).then(result => {
    if (!result.isConfirmed) return;
    $.post('', { action: 'delete', allocation_id: allocation_id }, function(resp) {
      if (resp && resp.status === 'success') {
        Swal.fire('Deleted','Allocation removed','success').then(()=> $('#subject').trigger('change'));
      } else {
        console.error('delete resp', resp);
        Swal.fire('Error', resp?.message || 'Failed to delete', 'error');
      }
    }, 'json').fail(function(xhr){ console.error('delete error', xhr.responseText); Swal.fire('Error','Failed to delete','error'); });
  });
}
</script>
</body>
</html>