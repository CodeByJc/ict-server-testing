<?php
// student_elective_allocation.php
// Simple elective allocation (no class). Stores rows into elective_allocation:
// (id, student_info_id, subject_info_id, sem_info_id).
//
// Workflow implemented:
// 1) Select semester -> loads elective subjects for that semester
// 2) Select subject -> list students of that semester and show allocation status
// 3) Add student -> INSERT INTO elective_allocation (student_info_id, subject_info_id, sem_info_id)
// 4) Remove student -> DELETE FROM elective_allocation WHERE id = ?
//
// Place this file in your faculty/admin folder. Requires ../../api/db/db_connection.php providing $conn (mysqli).

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_POST['action']);

    // 1) fetch_subjects by semester
    if ($action === 'fetch_subjects') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester ID']);
            exit;
        }

        $sql = "SELECT id, subject_name FROM subject_info WHERE sem_info_id = ? AND `type` = 'elective' ORDER BY subject_name";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
        mysqli_stmt_execute($stmt);
        $subjects = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'subjects' => $subjects]);
        exit;
    }

    // 2) fetch_students: list all students of that semester and include allocation info (ea.id)
    if ($action === 'fetch_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($sem_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester or subject ID']);
            exit;
        }

        // Left join elective_allocation to detect existing allocation for the subject.
        $sql = "
            SELECT
                si.id,
                si.first_name,
                si.last_name,
                si.gr_no,
                si.enrollment_no,
                ea.id AS allocation_id,
                ea.sem_info_id AS allocation_sem_info_id,
                ea.subject_info_id AS allocation_subject_info_id
            FROM student_info si
            LEFT JOIN elective_allocation ea
                ON si.id = ea.student_info_id AND ea.subject_info_id = ?
            WHERE si.sem_info_id = ?
            ORDER BY si.enrollment_no
        ";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) { echo json_encode(['status' => 'error', 'message' => 'DB prepare failed']); exit; }
        mysqli_stmt_bind_param($stmt, 'ii', $subject_id, $sem_id);
        mysqli_stmt_execute($stmt);
        $students = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'students' => $students]);
        exit;
    }

    // 3) fetch_available_students: students in sem not yet allocated to this subject
    if ($action === 'fetch_available_students') {
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        if ($sem_id <= 0 || $subject_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester or subject ID']);
            exit;
        }

        $sql = "
            SELECT si.id, si.first_name, si.last_name, si.enrollment_no
            FROM student_info si
            WHERE si.sem_info_id = ?
              AND si.id NOT IN (
                  SELECT student_info_id FROM elective_allocation WHERE subject_info_id = ?
              )
            ORDER BY si.first_name, si.last_name
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $sem_id, $subject_id);
        mysqli_stmt_execute($stmt);
        $students = fetch_all_stmt($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'students' => $students]);
        exit;
    }

    // 4) add_student: insert allocation WITHOUT class (store sem_info_id)
    if ($action === 'add_student') {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        $sem_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;

        if ($student_id <= 0 || $subject_id <= 0 || $sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid student, subject or semester ID']);
            exit;
        }

        // prevent duplicate
        $check = "SELECT id FROM elective_allocation WHERE student_info_id = ? AND subject_info_id = ? LIMIT 1";
        $cstmt = mysqli_prepare($conn, $check);
        mysqli_stmt_bind_param($cstmt, 'ii', $student_id, $subject_id);
        mysqli_stmt_execute($cstmt);
        mysqli_stmt_store_result($cstmt);
        if (mysqli_stmt_num_rows($cstmt) > 0) {
            mysqli_stmt_close($cstmt);
            echo json_encode(['status' => 'error', 'message' => 'Student already allocated to this subject']);
            exit;
        }
        mysqli_stmt_close($cstmt);

        $ins = "INSERT INTO elective_allocation (student_info_id, subject_info_id, sem_info_id) VALUES (?, ?, ?)";
        $istmt = mysqli_prepare($conn, $ins);
        if (!$istmt) { echo json_encode(['status' => 'error', 'message' => 'DB prepare failed']); exit; }
        mysqli_stmt_bind_param($istmt, 'iii', $student_id, $subject_id, $sem_id);
        $ok = mysqli_stmt_execute($istmt);
        $err = $ok ? '' : mysqli_stmt_error($istmt);
        mysqli_stmt_close($istmt);

        echo $ok ? json_encode(['status' => 'success', 'message' => 'Student allocated to subject']) : json_encode(['status' => 'error', 'message' => 'Failed to allocate: ' . $err]);
        exit;
    }

    // 5) delete_student: remove row from elective_allocation by student+subject or allocation id
    if ($action === 'delete_student') {
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        $subject_id = isset($_POST['subject_info_id']) ? intval($_POST['subject_info_id']) : 0;
        // allow deleting by allocation_id optionally
        $allocation_id = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($allocation_id > 0) {
            $del = "DELETE FROM elective_allocation WHERE id = ?";
            $dstmt = mysqli_prepare($conn, $del);
            mysqli_stmt_bind_param($dstmt, 'i', $allocation_id);
        } else {
            if ($student_id <= 0 || $subject_id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid student or subject ID']);
                exit;
            }
            $del = "DELETE FROM elective_allocation WHERE student_info_id = ? AND subject_info_id = ?";
            $dstmt = mysqli_prepare($conn, $del);
            mysqli_stmt_bind_param($dstmt, 'ii', $student_id, $subject_id);
        }

        $ok = mysqli_stmt_execute($dstmt);
        $err = $ok ? '' : mysqli_stmt_error($dstmt);
        mysqli_stmt_close($dstmt);

        echo $ok ? json_encode(['status' => 'success', 'message' => 'Allocation removed']) : json_encode(['status' => 'error', 'message' => 'Failed to remove: ' . $err]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// If GET, render the UI
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
        <button id="add-student-btn" class="bg-green-500 text-white px-6 py-2 rounded-full" disabled>Add Student</button>
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
          <th>Action</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
</div>

<script>
$(function(){
  let semId = null;
  let subjectId = null;

  const table = $('#student-table').DataTable({
    paging:false, info:false, searching:false, ordering:false,
    columns:[
      { data:'no' },
      { data:'student_name' },
      { data:'enrollment_no' },
      { data:'gr_no' },
      { data:'allocated' },
      { data:'action_html' }
    ]
  });

  // load elective subjects on semester change
  $('#semester').on('change', function(){
    semId = $(this).val();
    subjectId = null;
    $('#elective-subject').prop('disabled', true).empty().append('<option disabled selected>Loading...</option>');
    $('#add-student-btn').prop('disabled', true);
    table.clear().draw();

    if (!semId) return;

    $.post('', { action:'fetch_subjects', sem_info_id: semId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error', resp?.message || 'Failed to load subjects','error'); return; }
      $('#elective-subject').empty().append('<option disabled selected>Select Elective Subject</option>');
      resp.subjects.forEach(s => $('#elective-subject').append(`<option value="${s.id}">${s.subject_name}</option>`));
      $('#elective-subject').prop('disabled', resp.subjects.length === 0);
    }, 'json');
  });

  // when subject selected, list students of the semester with allocation status
  $('#elective-subject').on('change', function(){
    subjectId = $(this).val();
    table.clear().draw();
    $('#add-student-btn').prop('disabled', !subjectId);
    if (!semId || !subjectId) return;

    $.post('', { action:'fetch_students', sem_info_id: semId, subject_info_id: subjectId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error', resp?.message || 'Failed to load students','error'); return; }
      const rows = (resp.students || []).map((s,i) => ({
        no: i+1,
        student_name: `${s.first_name || ''} ${s.last_name || ''}`,
        enrollment_no: s.enrollment_no || '',
        gr_no: s.gr_no || '',
        allocated: s.allocation_id ? '<span class="text-green-600">YES</span>' : '<span class="text-gray-600">NO</span>',
        action_html: s.allocation_id
          ? `<button class="remove-btn text-red-500" data-allocation-id="${s.allocation_id}" data-student-id="${s.id}">Remove</button>`
          : `<button class="allocate-btn text-blue-600" data-student-id="${s.id}">Allocate</button>`
      }));
      table.clear().rows.add(rows).draw();
    }, 'json');
  });

  // allocate from inline button
  $('#student-table').on('click', '.allocate-btn', function(){
    const sid = $(this).data('student-id');
    if (!sid || !subjectId || !semId) return;
    $.post('', { action:'add_student', student_id: sid, subject_info_id: subjectId, sem_info_id: semId }, function(resp){
      if (resp && resp.status === 'success') {
        Swal.fire('Added','Student allocated to subject','success').then(()=> $('#elective-subject').trigger('change'));
      } else {
        Swal.fire('Error', resp?.message || 'Failed to allocate','error');
      }
    }, 'json');
  });

  // allocate via Add Student popup (select from available students)
  $('#add-student-btn').on('click', function(){
    if (!semId || !subjectId) return;
    $.post('', { action:'fetch_available_students', sem_info_id: semId, subject_info_id: subjectId }, function(resp){
      if (!resp || resp.status !== 'success') { Swal.fire('Error','Failed to load available students','error'); return; }
      const students = resp.students || [];
      if (!students.length) { Swal.fire('Info','No available students','info'); return; }
      let opts = '<option value="" disabled selected>Select Student</option>';
      students.forEach(s => opts += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`);
      Swal.fire({
        title: 'Allocate Student',
        html: `<select id="popup-student" class="w-full p-2 border">${opts}</select>`,
        showCancelButton: true,
        preConfirm: () => {
          const sid = $('#popup-student').val();
          if (!sid) { Swal.showValidationMessage('Select a student'); return false; }
          return sid;
        }
      }).then(result => {
        if (!result.isConfirmed) return;
        $.post('', { action:'add_student', student_id: result.value, subject_info_id: subjectId, sem_info_id: semId }, function(r){
          if (r && r.status === 'success') Swal.fire('Added','Student allocated','success').then(()=> $('#elective-subject').trigger('change'));
          else Swal.fire('Error', r?.message || 'Failed to add','error');
        }, 'json');
      });
    }, 'json');
  });

  // remove allocation (either by allocation_id or student+subject)
  $('#student-table').on('click', '.remove-btn', function(){
    const allocationId = $(this).data('allocation-id');
    const studentId = $(this).data('student-id');
    Swal.fire({ title:'Confirm', text:'Remove allocation?', icon:'warning', showCancelButton:true, confirmButtonText:'Yes' })
      .then(res => {
        if (!res.isConfirmed) return;
        $.post('', allocationId ? { action:'delete_student', allocation_id: allocationId } : { action:'delete_student', student_id: studentId, subject_info_id: subjectId }, function(r){
          if (r && r.status === 'success') Swal.fire('Removed','Allocation removed','success').then(()=> $('#elective-subject').trigger('change'));
          else Swal.fire('Error', r?.message || 'Failed to remove','error');
        }, 'json');
      });
  });

  $('#search-student').on('input', function(){ table.column(1).search(this.value, false, true).draw(); });
});
</script>
</body>
</html>