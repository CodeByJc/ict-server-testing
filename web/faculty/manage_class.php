<?php
// manage_class.php
// Fixed version: resolves bugs in fetch_class_info and cleans up dynamic IN binding.
// - Ensure all AJAX endpoints return valid JSON
// - Properly fetch faculty and subject names and attach to class rows
// - Semester + Batch dropdowns UI remains the same
//
// Replace your existing manage_class.php with this file.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php'); // expects $conn (mysqli)
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
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

function bind_params_dynamic($stmt, array $params) {
    if (empty($params)) return;
    $types = '';
    $refs = [];
    foreach ($params as $p) {
        // treat integer strings as ints if pure digits
        if (is_int($p)) $types .= 'i';
        else $types .= 's';
    }
    $refs[] = &$types;
    foreach ($params as $i => $val) $refs[] = &$params[$i];
    // call_user_func_array expects references
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function column_exists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if (!$res) return false;
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $exists;
}

// Helper: fetch rows for an IN(...) clause safely
function fetch_rows_by_ids($conn, $table, $id_col, $select_cols, $ids) {
    $out = [];
    if (empty($ids)) return $out;
    // sanitize ids to ints
    $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
    if (empty($ids)) return $out;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT {$select_cols} FROM {$table} WHERE {$id_col} IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return $out;
    $types = str_repeat('i', count($ids));
    $refs = [];
    $refs[] = &$types;
    foreach ($ids as $i => $v) $refs[] = &$ids[$i];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    mysqli_stmt_execute($stmt);
    $rows = fetch_all_stmt($stmt);
    mysqli_stmt_close($stmt);
    return $rows;
}

// -------------------- AJAX endpoints --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // Fetch batches for edu_type
    if ($action === 'fetch_batches') {
        $edu_type = isset($_POST['edu_type']) ? trim($_POST['edu_type']) : '';
        $sql = "SELECT id, batch_start_year, batch_end_year, edu_type FROM batch_info";
        $params = [];
        if ($edu_type !== '') {
            $sql .= " WHERE edu_type = ?";
            $params[] = $edu_type;
        }
        $sql .= " ORDER BY batch_start_year DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) { echo json_encode(['status'=>'error','message'=>'DB prepare failed']); exit; }
        if (!empty($params)) bind_params_dynamic($stmt, $params);
        mysqli_stmt_execute($stmt);
        $batches = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'success','batches'=>$batches]);
        exit;
    }

    // Fetch classes for sem + optional batch
    if ($action === 'fetch_class_info') {
        $sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;
        $batch_id = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? intval($_POST['batch_id']) : null;

        // Validate input
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        // Require batch_id to be selected
        if (is_null($batch_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Select a batch first']);
            exit;
        }

        // SQL to fetch class info for semester + batch
        $sql = "
            SELECT 
                ci.id AS class_id,
                ci.classname,
                ci.batch,
                ci.batch_id,
                ci.faculty_info_id
            FROM class_info ci
            WHERE ci.sem_info_id = ? AND ci.batch_id = ?
            ORDER BY ci.classname, ci.batch
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'DB prepare failed']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $batch_id);
        mysqli_stmt_execute($stmt);
        $classes = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        // If no classes
        if (empty($classes)) {
            echo json_encode(['status' => 'success', 'classes' => []]);
            exit;
        }

        // Map faculty names
        $faculty_map = [];
        $fids = array_values(array_unique(array_filter(array_column($classes, 'faculty_info_id'))));

        if (!empty($fids)) {
            $placeholders = implode(',', array_fill(0, count($fids), '?'));
            $types = str_repeat('i', count($fids));

            $sqlf = "
                SELECT 
                    id, 
                    COALESCE(CONCAT(first_name, ' ', last_name), '') AS faculty_name
                FROM faculty_info 
                WHERE id IN ($placeholders)
            ";

            $stmtf = mysqli_prepare($conn, $sqlf);
            $refs = [];
            $refs[] = &$types;
            foreach ($fids as $i => $fid) {
                $refs[] = &$fids[$i];
            }
            call_user_func_array([$stmtf, 'bind_param'], $refs);

            mysqli_stmt_execute($stmtf);
            $frows = fetch_all_stmt($stmtf);
            mysqli_stmt_close($stmtf);

            foreach ($frows as $fr) {
                $faculty_map[$fr['id']] = $fr['faculty_name'];
            }
        }

        // Attach faculty name
        foreach ($classes as &$c) {
            $fid = $c['faculty_info_id'] ?? null;
            $c['faculty_name'] = ($fid && isset($faculty_map[$fid])) ? $faculty_map[$fid] : '';
        }
        unset($c);

        echo json_encode(['status' => 'success', 'classes' => $classes]);
        exit;
    }



    // Fetch single class
    if ($action === 'fetch_class') {
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

        if ($class_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid class ID']);
            exit;
        }

        $sql = "
            SELECT 
                id AS class_id,
                classname,
                sem_info_id,
                batch,
                batch_id,
                faculty_info_id
            FROM class_info
            WHERE id = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'i', $class_id);
        mysqli_stmt_execute($stmt);
        $rows = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        if (empty($rows)) {
            echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        } else {
            echo json_encode(['status' => 'success', 'class' => $rows[0]]);
        }
        exit;
    }

    // Verify password
    if ($action === 'verify_password') {
        $password = $_POST['password'] ?? '';
        $session_username = $_SESSION['userdata']['username'] ?? $_SESSION['username'] ?? null;
        if (!$session_username) { echo json_encode(['status'=>'error','message'=>'No session user']); exit; }
        $sql = "SELECT password FROM user_login WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $session_username);
        mysqli_stmt_execute($stmt);
        $rows = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        $row = $rows[0] ?? null;
        if ($row && password_verify($password, $row['password'])) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error','message'=>'Incorrect password']);
        exit;
    }

    // Save class (create/update) - includes batch_id
    // Save class (create/update) - includes batch_id
if ($action === 'save_class') {
    $class_id = isset($_POST['class_id']) && $_POST['class_id'] !== '' ? intval($_POST['class_id']) : 0;
    $classname = isset($_POST['classname']) ? trim($_POST['classname']) : '';
    $sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : null;
    $batch = isset($_POST['batch']) ? trim($_POST['batch']) : '';
    $batch_id = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? intval($_POST['batch_id']) : null;
    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;

    // Validation
    if ($classname === '' || !$sem_id || !$faculty_id) {
        echo json_encode(['status'=>'error','message'=>'Missing required fields']);
        exit;
    }

    if ($class_id > 0) {
        // UPDATE existing class
        $sql = "UPDATE class_info 
                SET classname = ?, sem_info_id = ?, batch = ?, batch_id = ?, faculty_info_id = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sisiii',
            $classname,
            $sem_id,
            $batch,
            $batch_id,
            $faculty_id,
            $class_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status' => $ok ? 'success' : 'error',
                          'message' => $ok ? 'Class updated successfully' : 'Error updating class']);
        exit;
    } else {
        // INSERT new class
        $sql = "INSERT INTO class_info (classname, sem_info_id, batch, batch_id, faculty_info_id)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sisii',
            $classname,
            $sem_id,
            $batch,
            $batch_id,
            $faculty_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status' => $ok ? 'success' : 'error',
                          'message' => $ok ? 'Class created successfully' : 'Error creating class']);
        exit;
    }
}


    // Delete class
    if ($action === 'delete_class') {
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        if ($class_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid class id']); exit; }
        $sql = "DELETE FROM class_info WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $class_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Class deleted' : 'Error deleting class']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}
// -------------------- End AJAX --------------------
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Class</title>
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
<?php $page_title = "Manage Class"; include('./navbar.php'); ?>

<div class="p-5">
  <div class="bg-white shadow-xl rounded-xl p-3 mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
    <div>
      <label class="block text-sm font-medium">Semester</label>
      <select id="semester-select" class="mt-1 w-full p-2 border rounded">
        <option value="" disabled selected>Select Semester</option>
        <?php
        $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
        $sem_res = mysqli_query($conn, $sem_query);
        while ($s = mysqli_fetch_assoc($sem_res)) {
            $id = intval($s['id']);
            $label = "SEM " . intval($s['sem']) . " - " . strtoupper($s['edu_type']);
            $edu = htmlspecialchars($s['edu_type']);
            echo "<option value='{$id}' data-edu_type='{$edu}'>{$label}</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium">Batch (Cohort)</label>
      <select id="batch-filter" class="mt-1 w-full p-2 border rounded">
        <option value="">All Batches</option>
      </select>
    </div>

    <div class="flex gap-2">
      <button id="refresh-classes" class="bg-cyan-500 text-white px-4 py-2 rounded">Refresh</button>
      <button id="open-create" class="bg-green-500 text-white px-4 py-2 rounded">Create Class</button>
    </div>
  </div>

  <div id="class-container" class="bg-white shadow-xl rounded-xl p-3">
    <div id="class-tabs" class="min-h-40">
      <p class="text-sm text-gray-500">Select a semester and batch to view classes.</p>
    </div>
  </div>
</div>
</div><!-- main-content -->

<!-- Create/Edit popup -->
 <div id="create-class-popup" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
  <div class="bg-white p-6 rounded-lg w-4/12">
    <h3 id="popup-title" class="text-xl font-bold mb-4">Create New Class</h3>
    <form id="create-class-form" class="space-y-4">
      <!-- Hidden Fields -->
      <input type="hidden" name="class_id" id="popup-class-id">
      <!-- <input type="hidden" name="sem_id" id="popup-sem-id"> -->

      <!-- Class Name -->
      <div>
        <label for="classname" class="block font-semibold mb-1 text-gray-700">
          Class Name
        </label>
        <input type="text" 
               name="classname" 
               id="classname" 
               class="w-full p-2 border rounded focus:ring-2 focus:ring-cyan-400" 
               required>
      </div>
      

      <!-- Batch Code -->
      <div>
        <label for="batch" class="block font-semibold mb-1 text-gray-700">
          Batch Code (e.g. a, b, all)
        </label>
        <input type="text" 
               name="batch" 
               id="batch" 
               placeholder="a / b / all" 
               class="w-full p-2 border rounded focus:ring-2 focus:ring-cyan-400" 
               required>
      </div>

       <div class="mb-4">
        <label class="block font-semibold mb-1 text-gray-700">Semester</label>
        <select name="sem_id" id="sem_id" class="w-full p-2 border rounded focus:ring-2 focus:ring-cyan-400" required>
          <option value="" disabled selected>Select Semester</option>
          <?php
          $sem_q = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
          $sem_r = mysqli_query($conn, $sem_q);
          while ($s = mysqli_fetch_assoc($sem_r)) {
              $semLabel = "SEM " . htmlspecialchars($s['sem']) . " - " . strtoupper(htmlspecialchars($s['edu_type']));
              echo "<option value='" . intval($s['id']) . "'>$semLabel</option>";
          }
          ?>
        </select>
      </div>


      <!-- Batch (Cohort) -->
      <div>
        <label for="batch_id" class="block font-semibold mb-1 text-gray-700">
          Batch (Cohort)
        </label>
        <select name="batch_id" 
                id="batch_id" 
                class="w-full p-2 border rounded focus:ring-2 focus:ring-cyan-400">
          <option value="">Select Batch</option>
        </select>
      </div>

      <!-- Faculty -->
      <div>
        <label for="faculty_id" class="block font-semibold mb-1 text-gray-700">
          Select Faculty
        </label>
        <select name="faculty_id" 
                id="faculty_id" 
                class="w-full p-2 border rounded focus:ring-2 focus:ring-cyan-400" 
                required>
          <option value="" disabled selected>Select Faculty</option>
          <?php
          $fac_q = "
            SELECT 
              f.id, 
              COALESCE(CONCAT(f.first_name,' ',f.last_name), u.username) AS name 
            FROM faculty_info f 
            LEFT JOIN user_login u ON f.user_login_id = u.username 
            ORDER BY f.first_name, f.last_name
          ";
          $fac_r = mysqli_query($conn, $fac_q);
          while ($f = mysqli_fetch_assoc($fac_r)) {
              echo "<option value='" . intval($f['id']) . "'>" . htmlspecialchars($f['name']) . "</option>";
          }
          ?>
        </select>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t">
        <button type="button" 
                id="popup-cancel" 
                class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600 transition">
          Cancel
        </button>
        <button type="submit" 
                id="popup-submit" 
                class="px-4 py-2 rounded bg-cyan-500 text-white hover:bg-cyan-600 transition">
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
$(function(){
  function escapeHtml(text) {
    if (text === undefined || text === null) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function loadBatches(eduType) {
    $('#batch-filter').empty().append('<option value="">All Batches</option>');
    $('#batch_id').empty().append('<option value="">Select Batch Cohort</option>');
    if (!eduType) return;
    $.ajax({
      url: '',
      method: 'POST',
      dataType: 'json',
      data: { action: 'fetch_batches', edu_type: eduType },
      success: function(resp) {
        if (!resp || resp.status !== 'success') return;
        resp.batches.forEach(b => {
          const label = `${b.batch_start_year}-${b.batch_end_year}-${b.edu_type.toUpperCase()}`;
          $('#batch-filter').append(`<option value="${b.id}">${label}</option>`);
          $('#batch_id').append(`<option value="${b.id}">${label}</option>`);
        });
      }
    });
  }

  const $semSel = $('#semester-select');
  const initialSem = $semSel.find('option[data-edu_type]').first();
  if (initialSem.length) {
    initialSem.prop('selected', true);
    const semId = initialSem.val();
    const eduType = initialSem.data('edu_type');
    loadBatches(eduType);
    fetchClassTabs(semId, null);
    $('#popup-sem-id').val(semId);
  }

  $semSel.on('change', function(){
    const semId = $(this).val();
    const eduType = $(this).find('option:selected').data('edu_type');
    $('#popup-sem-id').val(semId);
    loadBatches(eduType);
    $('#batch-filter').val('');
    fetchClassTabs(semId, null);
  });

  $('#batch-filter').on('change', function(){
    const semId = $('#semester-select').val();
    const batchId = $(this).val() || null;
    fetchClassTabs(semId, batchId);
  });

  $('#refresh-classes').on('click', function(){
    const semId = $('#semester-select').val();
    const batchId = $('#batch-filter').val() || null;
    fetchClassTabs(semId, batchId);
  });

  function fetchClassTabs(semId, batchId) {
    if (!semId) {
        $('#class-tabs').html('<p class="text-sm text-gray-500">Select a semester first.</p>');
        return;
    }

    if (!batchId) {
        $('#class-tabs').html('<p class="text-sm text-gray-500">Select a batch first.</p>');
        return;
    }

    $.ajax({
        url: '', // PHP endpoint URL
        method: 'POST',
        dataType: 'json',
        data: { action: 'fetch_class_info', sem_id: semId, batch_id: batchId },
        success: function (resp) {
        if (!resp || resp.status !== 'success') {
            $('#class-tabs').html('<p class="text-sm text-gray-500">No classes or error loading data.</p>');
            return;
        }

        const classes = Array.isArray(resp.classes) ? resp.classes : [];
        let html = '';

        // Tabs header
        html += '<div class="flex flex-wrap gap-2 border-b pb-3">';
        if (classes.length > 0) {
            classes.forEach((c, i) => {
            const label = `${escapeHtml(c.classname)} - ${escapeHtml((c.batch || '').toUpperCase())}`;
            html += `
                <button class="class-tab px-4 py-2 -mb-px border-b-2 ${i === 0 ? 'tab-button-active' : 'tab-button-inactive'}"
                data-class-id="${c.class_id}" data-sem-id="${semId}">
                ${label}
                </button>
            `;
            });
        }

        // “+ New” button
        html += `
            <button id="create-new-class" class="px-4 py-2 -mb-px border-b-2 tab-button-inactive"
            data-sem-id="${semId}" data-batch-id="${batchId}">
            + New
            </button>
        `;
        html += '</div>';

        // Tab content
        html += '<div class="mt-4">';
        if (classes.length > 0) {
            classes.forEach((c, i) => {
            const facultyName = escapeHtml(c.faculty_name || '');
            html += `
                <div id="pane-${c.class_id}" class="pane ${i === 0 ? '' : 'hidden'} p-4 bg-gray-50 rounded-b-xl">
                <div class="flex justify-between items-center">
                    <div>
                    <h4 class="font-semibold text-gray-700">
                        ${escapeHtml(c.classname)} - ${escapeHtml((c.batch || '').toUpperCase())}
                    </h4>
                    <div class="text-sm text-gray-500">
                        ${facultyName ? 'Faculty: ' + facultyName : ''}
                    </div>
                    </div>
                    <div class="flex gap-2">
                    <button class="edit-class-btn px-4 py-1 rounded border border-blue-600 text-blue-600"
                        data-class-id="${c.class_id}" data-sem-id="${semId}">Edit</button>
                    <button class="delete-class-btn px-4 py-1 rounded border border-red-600 text-red-600"
                        data-class-id="${c.class_id}" data-sem-id="${semId}">Delete</button>
                    </div>
                </div>
                </div>
            `;
            });
        } else {
            html += `
            <div class="p-4 bg-gray-50 rounded-b-xl text-sm text-gray-600">
                No classes for this semester/batch.
                <button id="create-new-class-2" class="ml-2 px-3 py-1 rounded bg-gray-100 text-green-600 border border-green-400"
                data-sem-id="${semId}" data-batch-id="${batchId}">
                + Create Class
                </button>
            </div>
            `;
        }
        html += '</div>';

        $('#class-tabs').html(html);

        // Tab switching logic
        $('.class-tab').off('click').on('click', function () {
            $('.class-tab').removeClass('tab-button-active').addClass('tab-button-inactive');
            $(this).removeClass('tab-button-inactive').addClass('tab-button-active');
            const id = $(this).data('class-id');
            $('[id^="pane-"]').addClass('hidden');
            $(`#pane-${id}`).removeClass('hidden');
        });
        },
        error: function () {
        $('#class-tabs').html('<p class="text-sm text-red-500">Failed to load classes.</p>');
        }
    });
}

  // create triggers
  $(document).on('click', '#create-new-class, #create-new-class-2, #open-create', function(){
    const semId = $(this).data('sem-id') || $('#semester-select').val();
    if (!semId) return Swal.fire('Info','Select a semester first','info');
    openCreatePopup(semId, null);
  });

  // edit class
  $(document).on('click', '.edit-class-btn', function(){
    const classId = $(this).data('class-id');
    const semId = $(this).data('sem-id');
    $.ajax({
      url: '',
      method: 'POST',
      dataType: 'json',
      data: { action: 'fetch_class', class_id: classId},
      success: function(resp) {
        if (!resp || resp.status !== 'success') return Swal.fire('Error','Class not found','error');
        openCreatePopup(semId, resp.class);
      },
      error: function(){ Swal.fire('Error','Failed to fetch class details','error'); }
    });
  });

  // delete class (verify password then delete)
  $(document).on('click', '.delete-class-btn', function(){
    const classId = $(this).data('class-id');
    const semId = $(this).data('sem-id');
    Swal.fire({
      title: 'Enter your password',
      input: 'password',
      inputLabel: 'Password',
      showCancelButton: true,
      preConfirm: (pwd) => {
        if (!pwd) { Swal.showValidationMessage('Enter password'); return false; }
        return $.ajax({
          url: '',
          method: 'POST',
          dataType: 'json',
          data: { action: 'verify_password', password: pwd }
        }).then(function(res){
          if (!res || res.status !== 'success') throw new Error(res?.message || 'Verification failed');
          return res;
        }).catch(function(err){
          Swal.showValidationMessage(err.message || 'Verification failed');
        });
      },
      allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
      if (!result.isConfirmed) return;
      Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the class.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete'
      }).then((c) => {
        if (!c.isConfirmed) return;
        $.ajax({
          url: '',
          method: 'POST',
          dataType: 'json',
          data: { action: 'delete_class', class_id: classId },
          success: function(resp) {
            if (resp && resp.status === 'success') {
              Swal.fire('Deleted', resp.message, 'success').then(()=> {
                const semIdActive = $('#semester-select').val();
                const batchId = $('#batch-filter').val() || null;
                fetchClassTabs(semIdActive, batchId);
              });
            } else {
              Swal.fire('Error', resp?.message || 'Failed to delete', 'error');
            }
          },
          error: function(){ Swal.fire('Error','Delete request failed','error'); }
        });
      });
    });
  });

  // open popup for create/edit
  function openCreatePopup(semId, data) {
    $('#popup-sem-id').val(semId || '');
    $('#popup-class-id').val(data?.class_id || '');
    $('#classname').val(data?.classname || '');
    $('#batch').val((data?.batch || 'a'));
    $('#batch_id').val(data?.batch_id || '');
    $('#sem_id').val(semId || '');
    $('#faculty_id').val(data?.faculty_info_id || '');
    $('#popup-title').text(data ? 'Edit Class' : 'Create New Class');
    $('#popup-submit').text(data ? 'Update' : 'Create');
    // populate batch_id select from current batch-filter options (if any)
    $('#create-class-popup').removeClass('hidden');
  }

  $('#popup-cancel').on('click', function(){
    $('#create-class-popup').addClass('hidden');
    $('#create-class-form')[0].reset();
  });

  $('#create-class-form').on('submit', function(e){
    e.preventDefault();
    const data = $(this).serializeArray();
    data.push({ name: 'action', value: 'save_class' });
    $.ajax({
      url: '',
      method: 'POST',
      dataType: 'json',
      data: data,
      success: function(resp) {
        if (!resp) return Swal.fire('Error','No response','error');
        Swal.fire(resp.status === 'success' ? 'Success' : 'Error', resp.message, resp.status).then(() => {
          if (resp.status === 'success') {
            $('#create-class-popup').addClass('hidden');
            const semId = $('#popup-sem-id').val();
            const batchFilter = $('#batch-filter').val() || null;
            fetchClassTabs(semId, batchFilter);
          }
        });
      },
      error: function(){ Swal.fire('Error','Request failed','error'); }
    });
  });
});
</script>
</body>
</html>