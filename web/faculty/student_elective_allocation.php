<?php
// student_elective_allocation.php
// Elective allocation (student -> subject) with optional class assignment.
// This version adds per-row checkboxes (right side) and a bulk action bar
// at the bottom with "Allocate Selected" (no class) and "Allocate Selected + Assign Class".
//
// Requirements:
// - ../../api/db/db_connection.php must set $conn (mysqli).
// - Server-side supports POST actions: fetch_subjects, fetch_classes_for_sem, fetch_students,
//   fetch_available_students, add_students (accepts student_ids JSON array), delete_student.
//
// Notes:
// - This file is a single PHP page (AJAX endpoints + UI).
// - Tested logic assumes elective_allocation table and subject_info/student_info/class_info exist.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('../../api/db/db_connection.php');
if (!$conn) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
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

function column_exists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if (!$res) return false;
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $exists;
}

// ---------------- AJAX endpoints ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['action']);

    if ($action === 'fetch_subjects') {
        $sem_id = intval($_POST['sem_info_id'] ?? 0);
        if ($sem_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid semester ID']); exit; }
        $sql = "SELECT id, subject_name FROM subject_info WHERE sem_info_id = ? AND `type` = 'elective' ORDER BY subject_name";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $subjects = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'success','subjects'=>$subjects]);
        exit;
    }

    if ($action === 'fetch_classes_for_sem') {
        $sem_id = intval($_POST['sem_info_id'] ?? 0);
        if ($sem_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid semester ID']); exit; }
        $sql = "SELECT id, classname, batch FROM class_info WHERE sem_info_id = ? ORDER BY classname, batch";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $classes = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'success','classes'=>$classes]);
        exit;
    }

    if ($action === 'fetch_students') {
        $sem_id = intval($_POST['sem_info_id'] ?? 0);
        $subject_id = intval($_POST['subject_info_id'] ?? 0);
        if ($sem_id <= 0 || $subject_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid semester or subject ID']); exit; }
        $has_class_col = column_exists($conn, 'elective_allocation', 'class_info_id');
        if ($has_class_col) {
            $sql = "SELECT si.id, si.first_name, si.last_name, si.gr_no, si.enrollment_no, ea.id AS allocation_id, ea.class_info_id
                    FROM student_info si
                    LEFT JOIN elective_allocation ea ON si.id = ea.student_info_id AND ea.subject_info_id = ?
                    WHERE si.sem_info_id = ?
                    ORDER BY si.enrollment_no";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ii', $subject_id, $sem_id);
        } else {
            $sql = "SELECT si.id, si.first_name, si.last_name, si.gr_no, si.enrollment_no, ea.id AS allocation_id, NULL AS class_info_id
                    FROM student_info si
                    LEFT JOIN elective_allocation ea ON si.id = ea.student_info_id AND ea.subject_info_id = ?
                    WHERE si.sem_info_id = ?
                    ORDER BY si.enrollment_no";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ii', $subject_id, $sem_id);
        }
        mysqli_stmt_execute($stmt);
        $students = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'success','students'=>$students,'has_class_col'=>$has_class_col]);
        exit;
    }

    if ($action === 'fetch_available_students') {
        $sem_id = intval($_POST['sem_info_id'] ?? 0);
        $subject_id = intval($_POST['subject_info_id'] ?? 0);
        if ($sem_id <= 0 || $subject_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid semester or subject ID']); exit; }
        $sql = "SELECT si.id, si.first_name, si.last_name, si.enrollment_no
                FROM student_info si
                WHERE si.sem_info_id = ? AND si.id NOT IN (SELECT student_info_id FROM elective_allocation WHERE subject_info_id = ?)
                ORDER BY si.first_name, si.last_name";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $subject_id);
        mysqli_stmt_execute($stmt);
        $students = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['status'=>'success','students'=>$students]);
        exit;
    }

    if ($action === 'add_students') {
        $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
        $subject_id = intval($_POST['subject_info_id'] ?? 0);
        $sem_id = intval($_POST['sem_info_id'] ?? 0);
        $class_id = isset($_POST['class_id']) && $_POST['class_id'] !== '' ? intval($_POST['class_id']) : null;
        if (!is_array($student_ids) || empty($student_ids) || $subject_id <= 0 || $sem_id <= 0) {
            echo json_encode(['status'=>'error','message'=>'Invalid input: need student_ids array, subject_info_id and sem_info_id']); exit;
        }
        $has_class_col = column_exists($conn, 'elective_allocation', 'class_info_id');
        $inserted = 0; $skipped = 0; $errors = [];
        mysqli_begin_transaction($conn);
        try {
            foreach ($student_ids as $sid_raw) {
                $sid = intval($sid_raw);
                if ($sid <= 0) { $skipped++; continue; }
                $check = "SELECT id FROM elective_allocation WHERE student_info_id = ? AND subject_info_id = ? LIMIT 1";
                $cstmt = mysqli_prepare($conn, $check);
                mysqli_stmt_bind_param($cstmt, 'ii', $sid, $subject_id);
                mysqli_stmt_execute($cstmt);
                mysqli_stmt_store_result($cstmt);
                if (mysqli_stmt_num_rows($cstmt) > 0) { mysqli_stmt_close($cstmt); $skipped++; continue; }
                mysqli_stmt_close($cstmt);
                if ($has_class_col && $class_id !== null) {
                    $ins = "INSERT INTO elective_allocation (student_info_id, subject_info_id, sem_info_id, class_info_id) VALUES (?, ?, ?, ?)";
                    $istmt = mysqli_prepare($conn, $ins);
                    mysqli_stmt_bind_param($istmt, 'iiii', $sid, $subject_id, $sem_id, $class_id);
                } else {
                    $ins = "INSERT INTO elective_allocation (student_info_id, subject_info_id, sem_info_id) VALUES (?, ?, ?)";
                    $istmt = mysqli_prepare($conn, $ins);
                    mysqli_stmt_bind_param($istmt, 'iii', $sid, $subject_id, $sem_id);
                }
                if (!$istmt) { $errors[] = "DB prepare failed for student {$sid}: " . mysqli_error($conn); continue; }
                if (mysqli_stmt_execute($istmt)) $inserted++; else $errors[] = "Failed to insert student {$sid}: " . mysqli_stmt_error($istmt);
                mysqli_stmt_close($istmt);
            }
            if (!empty($errors)) { mysqli_rollback($conn); echo json_encode(['status'=>'error','message'=>'Some inserts failed','inserted'=>$inserted,'skipped'=>$skipped,'errors'=>$errors]); exit; }
            mysqli_commit($conn); echo json_encode(['status'=>'success','inserted'=>$inserted,'skipped'=>$skipped]); exit;
        } catch (Exception $e) {
            mysqli_rollback($conn); echo json_encode(['status'=>'error','message'=>'Transaction failed: '.$e->getMessage()]); exit;
        }
    }

    if ($action === 'delete_student') {
        $allocation_id = intval($_POST['allocation_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        $subject_id = intval($_POST['subject_info_id'] ?? 0);
        if ($allocation_id > 0) {
            $del = "DELETE FROM elective_allocation WHERE id = ?";
            $dstmt = mysqli_prepare($conn, $del);
            mysqli_stmt_bind_param($dstmt, 'i', $allocation_id);
        } else {
            if ($student_id <= 0 || $subject_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid student or subject ID']); exit; }
            $del = "DELETE FROM elective_allocation WHERE student_info_id = ? AND subject_info_id = ?";
            $dstmt = mysqli_prepare($conn, $del);
            mysqli_stmt_bind_param($dstmt, 'ii', $student_id, $subject_id);
        }
        $ok = mysqli_stmt_execute($dstmt);
        $err = $ok ? '' : mysqli_stmt_error($dstmt);
        mysqli_stmt_close($dstmt);
        echo $ok ? json_encode(['status'=>'success','message'=>'Allocation removed']) : json_encode(['status'=>'error','message'=>'Failed to remove: '.$err]);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Invalid action']);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Elective Allocation (student -> subject)</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  #student-table th, #student-table td { text-align:center; border:1px solid #d1d5db; }
  #student-table th { background:#374151; color:#fff; }
  .small { font-size:0.9rem; color:#6b7280; }
  select[multiple] { min-height:120px; }
  .bulk-bar {
    position: fixed;
    left: 18%;
    right: 2%;
    bottom: 18px;
    background: #fff;
    border: 1px solid #e6edf3;
    padding: 12px;
    border-radius: 10px;
    box-shadow: 0 6px 18px rgba(15,23,42,0.06);
    display: none;
    z-index: 60;
  }
</style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
<?php $page_title = "Student Elective Allocation"; include('./navbar.php'); ?>

<div class="container mx-auto p-6">
  <div class="bg-white p-6 rounded-xl shadow-md mb-6">
    <div class="flex flex-col md:flex-row md:space-x-4">
      <div class="w-full md:w-1/3">
        <label class="block font-semibold mb-2">Semester & Program</label>
        <select id="semester" class="w-full p-3 border-2 rounded-xl">
          <option value="" disabled selected>Select Semester & Program</option>
          <?php
          $sem_q = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
          $sem_r = mysqli_query($conn, $sem_q);
          while ($s = mysqli_fetch_assoc($sem_r)) {
            echo "<option value='{$s['id']}'>SEM {$s['sem']} - " . strtoupper($s['edu_type']) . "</option>";
          }
          ?>
        </select>
      </div>

      <div class="w-full md:w-1/3">
        <label class="block font-semibold mb-2">Elective Subject</label>
        <select id="elective-subject" class="w-full p-3 border-2 rounded-xl" disabled>
          <option value="" disabled selected>Select Elective Subject</option>
        </select>
      </div>
    </div>
  </div>

  <div class="p-6 bg-white rounded-xl shadow-md">
    <div class="flex justify-between items-center mb-6">
      <div class="flex space-x-4">
        <button id="add-student-btn" class="bg-green-500 text-white px-6 py-2 rounded-full" disabled>Add Student(s)</button>
      </div>
      <input id="search-student" class="w-64 p-2 border-2 rounded-xl" placeholder="Search student name...">
    </div>

    <table id="student-table" class="min-w-full bg-white">
      <thead>
        <tr class="bg-gray-700 text-white">
          <th>No</th>
          <th>Student Name</th>
          <th>Enrollment No</th>
          <th>GR No</th>
          <th>Allocated</th>
          <th>Class</th>
          <th>Action</th>
          <th>Select</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
</div>

<!-- Add Student modal (multi-select) -->
<div id="add-student-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center">
  <div class="bg-white p-6 rounded-lg w-96">
    <h3 class="text-lg font-semibold mb-4">Add Student(s) to Elective</h3>
    <div class="mb-3">
      <label class="block small mb-1">Select Students</label>
      <select id="popup-student" multiple class="w-full p-2 border rounded"></select>
      <p class="small mt-1 text-gray-600">Hold Ctrl (Windows) / Cmd (Mac) to select multiple students.</p>
    </div>
    <div class="mb-3">
      <label class="block small mb-1">Assign Class (optional)</label>
      <select id="popup-class" class="w-full p-2 border rounded">
        <option value="">-- None --</option>
      </select>
      <p id="class-warning" class="small mt-1 text-red-500 hidden">DB does not support storing class assignments for elective_allocation.</p>
    </div>
    <div class="flex justify-end gap-2">
      <button id="add-cancel" class="px-4 py-2 rounded bg-gray-500 text-white">Cancel</button>
      <button id="add-confirm" class="px-4 py-2 rounded bg-green-600 text-white">Add Selected</button>
    </div>
  </div>
</div>

<!-- Bulk action bar -->
<div class="bulk-bar" id="bulk-bar">
  <div class="flex items-center justify-between gap-4">
    <div>
      <span id="selected-count" class="small">0 selected</span>
    </div>
    <div class="flex gap-2">
      <button id="bulk-allocate" class="px-4 py-2 bg-cyan-500 text-white rounded">Allocate Selected (no class)</button>
      <button id="bulk-allocate-class" class="px-4 py-2 bg-indigo-600 text-white rounded">Allocate Selected + Assign Class</button>
      <button id="bulk-clear" class="px-3 py-2 bg-gray-200 rounded">Clear</button>
    </div>
  </div>
</div>

<script>
$(function(){
  let semId = null;
  let subjectId = null;
  let classesInSem = [];
  let hasClassCol = false;
  let selectedIds = new Set();

  const table = $('#student-table').DataTable({
    paging:false, info:false, searching:false, ordering:false,
    columns:[
      { data:'no' },
      { data:'student_name' },
      { data:'enrollment_no' },
      { data:'gr_no' },
      { data:'allocated' },
      { data:'class_name' },
      { data:'action_html' },
      { data:'select_html', orderable:false, searchable:false }
    ]
  });

  function updateBulkBar() {
    const count = selectedIds.size;
    if (count > 0) {
      $('#selected-count').text(`${count} selected`);
      $('#bulk-bar').show();
    } else {
      $('#bulk-bar').hide();
    }
  }

  // when semester changes, load subjects and classes
  $('#semester').on('change', function(){
    semId = $(this).val();
    subjectId = null;
    $('#elective-subject').prop('disabled', true).empty().append('<option disabled selected>Loading...</option>');
    $('#add-student-btn').prop('disabled', true);
    table.clear().draw();
    selectedIds.clear(); updateBulkBar();

    if (!semId) return;

    // fetch subjects
    $.post('', { action:'fetch_subjects', sem_info_id: semId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error', resp?.message || 'Failed to load subjects','error'); return; }
      $('#elective-subject').empty().append('<option disabled selected>Select Elective Subject</option>');
      resp.subjects.forEach(s => $('#elective-subject').append(`<option value="${s.id}">${s.subject_name}</option>`));
      $('#elective-subject').prop('disabled', resp.subjects.length === 0);
    }, 'json');

    // fetch classes for sem (for modal)
    $.post('', { action:'fetch_classes_for_sem', sem_info_id: semId }, function(resp){
      if (resp && resp.status === 'success') classesInSem = resp.classes || []; else classesInSem = [];
    }, 'json');
  });

  $('#elective-subject').on('change', function(){
    subjectId = $(this).val();
    table.clear().draw();
    $('#add-student-btn').prop('disabled', !subjectId);
    selectedIds.clear(); updateBulkBar();
    if (!semId || !subjectId) return;

    $.post('', { action:'fetch_students', sem_info_id: semId, subject_info_id: subjectId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error', resp?.message || 'Failed to load students','error'); return; }
      hasClassCol = !!resp.has_class_col;
      const rows = (resp.students || []).map((s,i) => {
        return {
          no: i+1,
          student_name: `${s.first_name || ''} ${s.last_name || ''}`.trim(),
          enrollment_no: s.enrollment_no || '',
          gr_no: s.gr_no || '',
          allocated: s.allocation_id ? '<span class="text-green-600">YES</span>' : '<span class="text-gray-600">NO</span>',
          class_name: s.class_info_id ? renderClassName(s.class_info_id) : '',
          action_html: s.allocation_id
            ? `<button class="remove-btn text-red-500" data-allocation-id="${s.allocation_id}" data-student-id="${s.id}">Remove</button>`
            : `<button class="allocate-btn text-blue-600" data-student-id="${s.id}">Allocate</button>`,
          select_html: `<input type="checkbox" class="select-row" data-student-id="${s.id}" ${s.allocation_id ? 'disabled' : ''}>`
        };
      });
      table.clear().rows.add(rows).draw();
    }, 'json');
  });

  function renderClassName(classId){
    const c = classesInSem.find(x => String(x.id) === String(classId));
    return c ? `${c.classname} - ${c.batch}` : '';
  }

  // row checkbox changes
  $('#student-table').on('change', '.select-row', function(){
    const sid = $(this).data('student-id');
    if ($(this).is(':checked')) selectedIds.add(String(sid)); else selectedIds.delete(String(sid));
    updateBulkBar();
  });

  // select all in current page (optional) - can add a header checkbox later
  $('#bulk-clear').on('click', function(){
    selectedIds.clear();
    $('#student-table .select-row').prop('checked', false);
    updateBulkBar();
  });

  // Bulk allocate without class
  $('#bulk-allocate').on('click', function(){
    if (selectedIds.size === 0) { Swal.fire('Info','No students selected','info'); return; }
    const ids = Array.from(selectedIds);
    Swal.fire({
      title: 'Confirm',
      text: `Allocate ${ids.length} students to this elective without class?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Allocate'
    }).then(res => {
      if (!res.isConfirmed) return;
      $.post('', { action:'add_students', student_ids: JSON.stringify(ids), subject_info_id: subjectId, sem_info_id: semId }, function(resp){
        if (resp && resp.status === 'success') {
          Swal.fire('Done', `Inserted: ${resp.inserted}, Skipped: ${resp.skipped}`, 'success').then(()=> {
            $('#elective-subject').trigger('change');
            selectedIds.clear(); updateBulkBar();
          });
        } else {
          Swal.fire('Error', resp?.message || 'Failed to allocate', 'error');
        }
      }, 'json');
    });
  });

  // Bulk allocate with class -> open class chooser modal then post with class_id
  $('#bulk-allocate-class').on('click', function(){
    if (selectedIds.size === 0) { Swal.fire('Info','No students selected','info'); return; }
    // if the DB doesn't support class, show warning and proceed without class
    // Build class options from classesInSem
    let classOptions = '<option value="">-- None --</option>';
    classesInSem.forEach(c => classOptions += `<option value="${c.id}">${c.classname} - ${c.batch}</option>`);
    Swal.fire({
      title: 'Assign class to selected',
      html: `<select id="swal-class" class="swal2-input">${classOptions}</select>`,
      focusConfirm: false,
      showCancelButton: true,
      preConfirm: () => {
        return $('#swal-class').val();
      }
    }).then(result => {
      if (!result.isConfirmed) return;
      const classId = result.value || null;
      const ids = Array.from(selectedIds);
      $.post('', { action:'add_students', student_ids: JSON.stringify(ids), subject_info_id: subjectId, sem_info_id: semId, class_id: classId }, function(resp){
        if (resp && resp.status === 'success') {
          Swal.fire('Done', `Inserted: ${resp.inserted}, Skipped: ${resp.skipped}`, 'success').then(()=> {
            $('#elective-subject').trigger('change');
            selectedIds.clear(); updateBulkBar();
          });
        } else {
          Swal.fire('Error', resp?.message || 'Failed to allocate', 'error');
        }
      }, 'json');
    });
  });

  // inline allocate opens modal with that student preselected
  $('#student-table').on('click', '.allocate-btn', function(){
    const sid = $(this).data('student-id');
    openAddStudentModal([sid]);
  });

  // Add Student button opens modal without preselection
  $('#add-student-btn').on('click', function(){ openAddStudentModal([]); });

  function openAddStudentModal(preselectedIds){
    if (!semId || !subjectId) return;
    $.post('', { action:'fetch_available_students', sem_info_id: semId, subject_info_id: subjectId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error','Failed to load available students','error'); return; }
      const students = resp.students || [];
      if (!students.length) { Swal.fire('Info','No available students to add','info'); return; }
      const $student = $('#popup-student').empty();
      students.forEach(s => {
        const sel = preselectedIds.includes(String(s.id)) || preselectedIds.includes(Number(s.id)) ? 'selected' : '';
        $student.append(`<option value="${s.id}" ${sel}>${s.first_name} ${s.last_name}</option>`);
      });
      const $class = $('#popup-class').empty();
      $class.append('<option value="">-- None --</option>');
      classesInSem.forEach(c => $class.append(`<option value="${c.id}">${c.classname} - ${c.batch}</option>`));
      if (!hasClassCol) $('#class-warning').removeClass('hidden'); else $('#class-warning').addClass('hidden');
      $('#add-student-modal').removeClass('hidden');
    }, 'json');
  }

  $('#add-cancel').on('click', function(){ $('#add-student-modal').addClass('hidden'); });
  $('#add-confirm').on('click', function(){
    const selected = $('#popup-student').val();
    const cid = $('#popup-class').val() || null;
    if (!selected || (Array.isArray(selected) && selected.length === 0)) { Swal.fire('Error','Select at least one student','error'); return; }
    const ids = Array.isArray(selected) ? selected.map(x=>parseInt(x)) : [parseInt(selected)];
    $.post('', { action:'add_students', student_ids: JSON.stringify(ids), subject_info_id: subjectId, sem_info_id: semId, class_id: cid }, function(resp){
      if (!resp) { Swal.fire('Error','No response from server','error'); return; }
      if (resp.status === 'success') {
        Swal.fire('Added', `Inserted: ${resp.inserted}, Skipped: ${resp.skipped}`, 'success').then(()=> {
          $('#add-student-modal').addClass('hidden');
          $('#elective-subject').trigger('change');
        });
      } else {
        Swal.fire('Error', resp.message || 'Failed to add students', 'error');
      }
    }, 'json');
  });

  // remove allocation
  $('#student-table').on('click', '.remove-btn', function(){
    const allocationId = $(this).data('allocation-id');
    const studentId = $(this).data('student-id');
    Swal.fire({ title:'Confirm', text:'Remove allocation?', icon:'warning', showCancelButton:true, confirmButtonText:'Yes' })
    .then(res => {
      if (!res.isConfirmed) return;
      const payload = allocationId ? { action:'delete_student', allocation_id: allocationId } : { action:'delete_student', student_id: studentId, subject_info_id: subjectId };
      $.post('', payload, function(r){
        if (r && r.status === 'success') Swal.fire('Removed','Allocation removed','success').then(()=> $('#elective-subject').trigger('change'));
        else Swal.fire('Error', r?.message || 'Failed to remove','error');
      }, 'json');
    });
  });

  $('#search-student').on('input', function(){ table.column(1).search(this.value, false, true).draw(); });

  // ensure bulk-bar hidden initially
  $('#bulk-bar').hide();
});
</script>
</body>
</html>