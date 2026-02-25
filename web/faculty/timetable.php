<?php
include('../../api/db/db_connection.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Timetable Manage</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <style>
    .tab-active{background-color:#3b82f6;color:white;}
    .tile{background:#e5e7eb;padding:1rem;border-radius:0.5rem;margin-bottom:1rem;position:relative;}
    .saved-tag{position:absolute;top:.5rem;right:.5rem;padding:.25rem .5rem;border-radius:.25rem;font-size:.75rem;color:#fff;}
    .saved{background:#22c55e;}
    .not-saved{background:#eab308;}
  </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
  <?php $page_title = "Timetable Manage"; include('./navbar.php'); ?>
  <div class="container mx-auto p-6">
    <form id="timetableForm" class="bg-white p-6 rounded-xl shadow-md">
      <div class="flex flex-wrap -mx-3 mb-4">
        <!-- Semester -->
        <div class="w-full md:w-1/3 px-3 mb-4">
          <label for="semester-select" class="block text-gray-700 font-bold mb-2">Semester & Program</label>
          <select id="semester-select" name="semester" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500">
            <option value="" disabled selected>Select Semester & Program</option>
            <?php
            $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
            $sem_result = mysqli_query($conn, $sem_query);
            while ($row = mysqli_fetch_assoc($sem_result)) {
                $edu = htmlspecialchars($row['edu_type']);
                echo "<option value='{$row['id']}' data-edu_type='{$edu}'>SEM {$row['sem']} - " . strtoupper($edu) . "</option>";
            }
            ?>
          </select>
        </div>

        <!-- Batch (top-level) -->
        <div class="w-full md:w-1/3 px-3 mb-4">
          <label for="batch-select" class="block text-gray-700 font-bold mb-2">Batch (Cohort)</label>
          <select id="batch-select" name="batch_select" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500" disabled>
            <option value="">Select Batch (Cohort)</option>
          </select>
        </div>

        <!-- Class & Batch (class code + batch code) -->
        <div class="w-full md:w-1/3 px-3 mb-4">
          <label for="class_batch" class="block text-gray-700 font-bold mb-2">Class & Batch</label>
          <select id="class_batch" name="class_batch" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500" disabled>
            <option value="" disabled selected>Select Class & Batch</option>
          </select>
        </div>
      </div>

      <div id="day_tabs" class="mb-4 hidden">
        <div class="flex space-x-2 border-b">
          <?php
          $days = ['monday','tuesday','wednesday','thursday','friday'];
          foreach ($days as $index=>$day) {
              $active = $index===0 ? 'tab-active' : '';
              echo "<button type='button' class='tab-button px-4 py-2 font-bold text-sm capitalize rounded-full $active hover:bg-cyan-600 hover:text-white transition-all' data-day='$day'>$day</button>";
          }
          ?>
        </div>
      </div>

      <div id="timetable_slots" class="mb-4">
        <?php foreach ($days as $day) { ?>
          <div id="slots_<?php echo $day; ?>" class="day-slots hidden">
            <h3 class="text-lg font-semibold capitalize mb-2"><?php echo $day; ?> Slots</h3>
            <div id="tiles_<?php echo $day; ?>" class="tiles"></div>
            <button type="button" class="add-slot bg-cyan-500 text-white px-3 p-1 rounded-full hover:scale-110 hover:bg-cyan-600 font-bold transition-all mt-3 mb-2" data-day="<?php echo $day; ?>" disabled>+ Add Slot</button>
          </div>
        <?php } ?>
      </div>

      <div id="slot_form" class="hidden bg-gray-100 p-4 rounded-xl mb-4">
        <h3 class="text-lg font-semibold mb-2">Add/Edit Slot</h3>
        <input type="hidden" id="slot_day" name="slot_day">
        <input type="hidden" id="edit_index" name="edit_index">
        <input type="hidden" id="slot_id" name="slot_id">

        <!-- Popup-level Semester + Batch (user requested explicit batch_info_id) -->
        <div class="flex flex-wrap -mx-3">
          <div class="w-full md:w-1/3 px-3 mb-4">
            <label for="popup-sem-id" class="block text-gray-700 font-semibold mb-1">Semester</label>
            <select id="popup-sem-id" name="sem_id" class="w-full p-2 border rounded" required>
              <option value="">Select Semester</option>
              <?php
              // reload sem_info options for popup
              $sem_q2 = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
              $sem_r2 = mysqli_query($conn, $sem_q2);
              while ($s2 = mysqli_fetch_assoc($sem_r2)) {
                  $edu2 = htmlspecialchars($s2['edu_type']);
                  echo "<option value='{$s2['id']}' data-edu_type='{$edu2}'>SEM {$s2['sem']} - " . strtoupper($edu2) . "</option>";
              }
              ?>
            </select>
          </div>

          <div class="w-full md:w-1/3 px-3 mb-4">
            <label for="batch_id" class="block text-gray-700 font-semibold mb-1">Batch (Cohort)</label>
            <select id="batch_id" name="batch_id" class="w-full p-2 border rounded">
              <option value="">Select Batch Cohort</option>
            </select>
          </div>

          <div class="w-full md:w-1/3 px-3 mb-4">
            <label for="class_location" class="block text-gray-700 font-semibold mb-1">Class Location</label>
            <select id="class_location" name="class_location" class="w-full p-2 border rounded">
              <option value="" disabled selected>Select Class Location</option>
              <?php
              $loc_query = "SELECT id, classname FROM class_location_info";
              $loc_result = mysqli_query($conn, $loc_query);
              while ($row_loc = mysqli_fetch_assoc($loc_result)) {
                  echo "<option value='".intval($row_loc['id'])."'>".htmlspecialchars($row_loc['classname'])."</option>";
              }
              ?>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap -mx-3">
          <div class="w-full md:w-1/2 px-3 mb-4">
            <label for="start_time" class="block text-gray-700 font-bold mb-2">Start Time</label>
            <input type="time" id="start_time" name="start_time" class="w-full p-3 border-2 rounded-xl">
          </div>
          <div class="w-full md:w-1/2 px-3 mb-4">
            <label for="end_time" class="block text-gray-700 font-bold mb-2">End Time</label>
            <input type="time" id="end_time" name="end_time" class="w-full p-3 border-2 rounded-xl">
          </div>
          <div class="w-full md:w-1/2 px-3 mb-4">
            <label for="subject" class="block text-gray-700 font-bold mb-2">Subject</label>
            <select id="subject" name="subject" class="w-full p-3 border-2 rounded-xl" disabled>
              <option value="" disabled selected>Select Subject</option>
            </select>
          </div>
          <div class="w-full md:w-1/2 px-3 mb-4">
            <label for="faculty" class="block text-gray-700 font-bold mb-2">Faculty</label>
            <select id="faculty" name="faculty" class="w-full p-3 border-2 rounded-xl" disabled>
              <option value="" disabled selected>Select Faculty</option>
            </select>
          </div>
          <div class="w-full md:w-1/2 px-3 mb-4">
            <label for="lec_type" class="block text-gray-700 font-bold mb-2">Lecture Type</label>
            <select id="lec_type" name="lec_type" class="w-full p-3 border-2 rounded-xl">
              <option value="" disabled selected>Select Lecture Type</option>
              <option value="T">Theory</option>
              <option value="L">Lab</option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex justify-end space-x-2">
          <button type="button" id="cancel_slot" class="bg-gray-700 text-white px-5 p-3 rounded-full">Cancel</button>
          <button type="button" id="save_slot" class="bg-cyan-600 text-white px-5 p-3 rounded-full">Save Slot</button>
        </div>
      </div>

      <div class="text-center mt-4">
        <button type="button" id="submit_timetable" class="bg-cyan-600 text-white px-5 p-3 rounded-full" disabled>Submit Timetable</button>
      </div>
    </form>
  </div>
</div>

<script>
$(function(){
  // State
  let slots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
  let savedSlots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
  let selectedSemId = '';
  let selectedBatchInfoId = '';
  let selectedClassId = '';

  // Helpers
  function escapeHtml(text){ if (text===undefined||text===null) return ''; return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
  function showError(msg){ Swal.fire('Error', msg, 'error'); }
  function showWarning(msg){ Swal.fire('Warning', msg, 'warning'); }
  function showSuccess(msg){ Swal.fire('Success', msg, 'success'); }

  // Tab switching
  $(document).on('click', '.tab-button', function(){
    $('.tab-button').removeClass('tab-active');
    $(this).addClass('tab-active');
    $('.day-slots').addClass('hidden');
    $('#slots_' + $(this).data('day')).removeClass('hidden');
  });

  // Initialize: choose first semester option automatically if present
  const $sem = $('#semester-select');
  if ($sem.find('option').length>1) {
    $sem.find('option').not('[disabled]').first().prop('selected', true);
    $sem.trigger('change');
  }

  // When semester changes: load batches (batch_info) and clear downstream selects
  $sem.on('change', function(){
    selectedSemId = $(this).val() || '';
    selectedBatchInfoId = '';
    $('#batch-select').prop('disabled', true).html('<option value="">Loading...</option>');
    $('#class_batch').prop('disabled', true).html('<option value="" disabled selected>Select Class & Batch</option>');
    $('#day_tabs').addClass('hidden');
    $('.tiles').empty();
    $('.add-slot').prop('disabled', true);
    $('#submit_timetable').prop('disabled', true);
    slots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
    savedSlots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};

    if (!selectedSemId) {
      $('#batch-select').prop('disabled', true).html('<option value="">Select Batch (Cohort)</option>');
      return;
    }

    // fetch batches for the semester's edu_type (server endpoint: fetch_batches.php)
    const eduType = $('#semester-select option:selected').data('edu_type') || '';
    $.ajax({
      url: 'fetch_batches.php',
      method: 'POST',
      dataType: 'json',
      data: { edu_type: eduType },
      success: function(resp){
        let batches = [];
        if (Array.isArray(resp)) batches = resp;
        else if (resp && resp.status === 'success' && Array.isArray(resp.batches)) batches = resp.batches;
        else { $('#batch-select').prop('disabled', true).html('<option value="">Select Batch (Cohort)</option>'); return; }

        $('#batch-select').prop('disabled', false).html('<option value="">Select Batch (Cohort)</option>');
        batches.forEach(b => { $('#batch-select').append(`<option value="${b.id}">${b.batch_start_year}-${b.batch_end_year}-${b.edu_type}</option>`); });
      },
      error: function(xhr,status,err){ console.error('fetch_batches error',status,err,xhr.responseText); $('#batch-select').prop('disabled', true).html('<option value="">Select Batch (Cohort)</option>'); }
    });
  });

  // When batch (top-level) changes: load classes filtered by sem + batch
  $('#batch-select').on('change', function(){
    selectedBatchInfoId = $(this).val() || '';
    $('#class_batch').prop('disabled', true).html('<option value="" disabled selected>Loading...</option>');
    $('#day_tabs').addClass('hidden');
    $('.tiles').empty();
    $('.add-slot').prop('disabled', true);
    $('#submit_timetable').prop('disabled', true);
    slots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
    savedSlots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};

    if (!selectedSemId || !selectedBatchInfoId) {
      $('#class_batch').prop('disabled', true).html('<option value="" disabled selected>Select Class & Batch</option>');
      return;
    }

    // fetch classes for semester + batch_info_id
    $.ajax({
      url: 'fetch_classes.php',
      method: 'POST',
      dataType: 'json',
      data: { sem_id: selectedSemId, batch_id: selectedBatchInfoId },
      success: function(resp){
        let classes = [];
        if (Array.isArray(resp)) classes = resp;
        else if (resp && resp.status === 'success' && Array.isArray(resp.classes)) classes = resp.classes;
        else { showError('Invalid response while loading classes'); $('#class_batch').prop('disabled',true); return; }

        $('#class_batch').prop('disabled', false).html('<option value="" disabled selected>Select Class & Batch</option>');
        if (classes.length===0) { showWarning('No classes found for this semester and batch'); $('#class_batch').prop('disabled',true); return; }

        classes.forEach(cls=>{
          const electiveSub = (cls.elective_subject_id !== undefined && cls.elective_subject_id !== null) ? cls.elective_subject_id : '';
          const batchIdAttr = (cls.batch_id !== undefined && cls.batch_id !== null) ? cls.batch_id : '';
          $('#class_batch').append(
            `<option value="${cls.id}" data-classname="${escapeHtml(cls.classname)}" data-batch="${escapeHtml(cls.batch)}" data-group="${escapeHtml(cls.group||'')}" data-elective-sub-id="${escapeHtml(electiveSub)}" data-batch-id="${escapeHtml(batchIdAttr)}">${escapeHtml(cls.classname)} - ${(cls.batch||'').toUpperCase()}${(cls.group==='elective')?' (Elective)':''}</option>`
          );
        });
      },
      error: function(xhr,status,err){ console.error('fetch_classes error',status,err,xhr.responseText); showError('Failed to load classes'); $('#class_batch').prop('disabled', true); }
    });
  });

  // On class selection: enable tabs and buttons, load timetable
  $('#class_batch').on('change', function(){
    selectedClassId = $(this).val() || '';
    // prefer top-level selectedBatchInfoId; fallback to option data
    const batchIdFromOption = $('#class_batch option:selected').data('batch-id') || selectedBatchInfoId || '';
    if (batchIdFromOption) $('#batch_id').val(batchIdFromOption);
    $('#day_tabs').removeClass('hidden');
    $('.add-slot').prop('disabled', false);
    $('#submit_timetable').prop('disabled', false);
    loadTimetable(selectedSemId, selectedClassId);
  });

  // When popup semester changes -> load batch cohorts and elective subjects if needed
  $('#popup-sem-id').on('change', function(){
    const semId = $(this).val();
    const eduType = $(this).find('option:selected').data('edu_type') || $('#semester-select option[value="'+semId+'"]').data('edu_type');
    $('#batch_id').empty().append('<option value="">Loading...</option>');
    if (!eduType) return;
    $.ajax({
      url: 'fetch_batches.php',
      method: 'POST',
      dataType: 'json',
      data: { edu_type: eduType },
      success: function(resp){
        let batches = [];
        if (Array.isArray(resp)) batches = resp;
        else if (resp && resp.status === 'success' && Array.isArray(resp.batches)) batches = resp.batches;
        else { $('#batch_id').empty().append('<option value="">Select Batch Cohort</option>'); return; }
        $('#batch_id').empty().append('<option value="">Select Batch Cohort</option>');
        batches.forEach(b=> $('#batch_id').append(`<option value="${b.id}">${b.batch_start_year}-${b.batch_end_year}-${b.edu_type}</option>`));
      },
      error: function(){ $('#batch_id').empty().append('<option value="">Select Batch Cohort</option>'); }
    });

    if ($('#group').val() === 'elective') loadSubjects();
  });

  // Load subjects (handles both regular and elective)
  function loadSubjects(isEditing = false, preSelected = null) {
  $('#subject')
    .prop('disabled', true)
    .html('<option value="">Loading...</option>');

  const semForPopup = $('#popup-sem-id').val() || selectedSemId;

  $.ajax({
    url: 'fetch_subjects.php',
    method: 'POST',
    dataType: 'json',
    contentType: 'application/json; charset=utf-8',
    data: JSON.stringify({ sem_id: semForPopup }), // ✅ send JSON body
    success: function (resp) {
      let subjects = [];

      // ✅ handle the API response structure
      if (Array.isArray(resp)) {
        // direct array
        subjects = resp;
      } else if (resp.status === 'success' && Array.isArray(resp.subjects)) {
        // wrapped array with status
        subjects = resp.subjects;
      } else if (resp.error) {
        showError(resp.error);
        $('#subject').prop('disabled', true);
        return;
      } else {
        showError('Invalid response while loading subjects');
        $('#subject').prop('disabled', true);
        return;
      }

      // ✅ Populate dropdown
      $('#subject')
        .empty()
        .append('<option value="" disabled selected>Select Subject</option>');

      subjects.forEach((s) => {
        $('#subject').append(`
          <option value="${s.id}" data-lec-type="${s.lec_type || ''}">
            ${escapeHtml(s.short_name || '')} - ${escapeHtml(s.subject_name || '')}
          </option>
        `);
      });

      // ✅ If editing, restore the previously selected subject
      if (isEditing && preSelected) {
        $('#subject').val(preSelected).prop('disabled', false);
        loadFaculty(preSelected, selectedClassId);
      } else {
        $('#subject').prop('disabled', false);
      }
    },
    error: function (xhr, status, err) {
      console.error('fetch_subjects error', status, err, xhr.responseText);
      showError('Failed to load subjects');
      $('#subject').prop('disabled', true);
    }
  });
}




  // Load faculty for a subject+class
  function loadFaculty(subjectId, classId, selectedFacultyId = null) {
    $('#faculty').prop('disabled', true).html('<option value="" disabled selected>Loading...</option>');
    if (!subjectId || !classId) { $('#faculty').empty().append('<option value="" disabled selected>Select Faculty</option>'); return; }
    $.ajax({
      url: 'fetch_faculty.php',
      method: 'POST',
      dataType: 'json',
      data: { subject_id: subjectId, class_id: classId },
      success: function(resp){
        let facs = [];
        if (Array.isArray(resp)) facs = resp;
        else if (resp && resp.status === 'success' && Array.isArray(resp.faculty)) facs = resp.faculty;
        else { showError('Invalid faculty response'); $('#faculty').prop('disabled', true); return; }

        if (facs.length === 0) {
          Swal.fire({icon:'warning', title:'No Faculty Assigned', text:'No faculty are assigned to this subject/class.'});
          $('#faculty').prop('disabled', true).html('<option value="" disabled selected>No Faculty</option>');
          return;
        }

        $('#faculty').empty().append('<option value="" disabled selected>Select Faculty</option>');
        facs.forEach(f => $('#faculty').append(`<option value="${f.id}">${escapeHtml(f.first_name||'')}${f.last_name?(' '+escapeHtml(f.last_name)):''}</option>`));
        if (selectedFacultyId) $('#faculty').val(selectedFacultyId);
        else if (facs.length === 1) $('#faculty').val(facs[0].id);
        $('#faculty').prop('disabled', false);
      },
      error: function(xhr,status,err){ console.error('fetch_faculty error',status,err,xhr.responseText); showError('Failed to load faculty'); $('#faculty').prop('disabled', true); }
    });
  }

  // Configure lecture type dropdown
  function configureLectureType(lecType) {
    const $lec = $('#lec_type');
    $lec.prop('disabled', false);
    if (!lecType) { $lec.val(''); return; }
    if (lecType === 'T') $lec.val('T').prop('disabled', true);
    else if (lecType === 'L') $lec.val('L').prop('disabled', true);
    else $lec.val('');
  }

  // Load timetable for selected sem + class
  function loadTimetable(semId, classId) {
    $.ajax({
      url: 'fetch_timetable.php',
      method: 'POST',
      dataType: 'json',
      data: { sem_id: semId, class_id: classId },
      success: function(resp){
        let timetable = [];
        if (Array.isArray(resp)) timetable = resp;
        else if (resp && resp.status === 'success' && Array.isArray(resp.timetable)) timetable = resp.timetable;
        else { showError('Invalid timetable response'); return; }

        slots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
        savedSlots = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[]};
        timetable.forEach(slot=>{
          const day = slot.day;
          if (!day) return;
          savedSlots[day] = savedSlots[day] || [];
          savedSlots[day].push({
            id: slot.id || slot.slot_id || null,
            start_time: slot.start_time,
            end_time: slot.end_time,
            class_id: slot.class_info_id || slot.class_id,
            subject_id: slot.subject_info_id || slot.subject_id,
            faculty_id: slot.faculty_info_id || slot.faculty_id,
            location_id: slot.class_location_info_id || slot.location_id,
            lec_type: slot.lec_type,
            classname: slot.classname || '',
            batch: slot.batch || '',
            subject_name: slot.subject_name || slot.subject || '',
            faculty_name: slot.faculty_name || ''
          });
        });
        ['monday','tuesday','wednesday','thursday','friday'].forEach(d=>updateTiles(d));
      },
      error: function(xhr,status,err){ console.error('fetch_timetable error',status,err,xhr.responseText); showError('Failed to load timetable'); }
    });
  }

  // Add slot button handler
  $(document).on('click', '.add-slot', function(){
    if (!selectedClassId) return showError('Please select a class first');
    $('#slot_day').val($(this).data('day'));
    $('#edit_index').val('');
    $('#slot_id').val('');
    $('#start_time,#end_time,#class_location,#lec_type').val('');
    $('#subject,#faculty').empty().prop('disabled', true).html('<option value="" disabled selected>Select Subject</option>');
    // set popup semester default to page semester
    $('#popup-sem-id').val(selectedSemId).trigger('change');
    // set popup batch to top-level batch if available
    const topBatch = $('#batch-select').val() || $('#class_batch option:selected').data('batch-id') || '';
    if (topBatch) $('#batch_id').val(topBatch);
    // load subjects for that popup sem and selected class
    loadSubjects(false, null);
    $('#slot_form').removeClass('hidden');
  });

  // Cancel slot
  $('#cancel_slot').on('click', function(){ $('#slot_form').addClass('hidden'); });

  // Edit slot
  $(document).on('click', '.edit-slot', function(){
    const day = $(this).data('day');
    const idx = $(this).data('index');
    const isSaved = $(this).hasClass('saved-slot');
    const slot = isSaved ? savedSlots[day][idx] : slots[day][idx];
    if (!slot) return showError('Slot not found');

    $('#slot_day').val(day);
    $('#edit_index').val(isSaved ? '' : idx);
    $('#slot_id').val(slot.id || '');
    $('#start_time').val(slot.start_time);
    $('#end_time').val(slot.end_time);
    $('#batch_id').val(slot.batch_id || $('#batch-select').val() || '');
    $('#class_location').val(slot.location_id || '');
    $('#lec_type').val(slot.lec_type || '');
    $('#popup-sem-id').val(slot.sem_id || selectedSemId).trigger('change');
    // load subjects then faculty (loadSubjects will auto populate/select when editing)
    loadSubjects(true, slot.subject_id);
    setTimeout(()=> loadFaculty(slot.subject_id, selectedClassId, slot.faculty_id), 450);
    $('#slot_form').removeClass('hidden');
  });

  // Save slot (update remote if slotId present; else add local unsaved or update local)
  $('#save_slot').on('click', function(){
    const day = $('#slot_day').val();
    const startTime = $('#start_time').val();
    const endTime = $('#end_time').val();
    const subjectId = $('#subject').val();
    const facultyId = $('#faculty').val();
    const locationId = $('#class_location').val();
    const lecType = $('#lec_type').val();
    const editIndex = $('#edit_index').val();
    const slotId = $('#slot_id').val();
    const classId = selectedClassId;
    const batchId = $('#batch_id').val() || $('#batch-select').val() || $('#class_batch option:selected').data('batch-id') || '';

    if (!startTime||!endTime||!subjectId||!facultyId||!locationId||!lecType) return showError('Please fill all fields');

    const payload = {
      day, start_time: startTime, end_time: endTime,
      subject_id: subjectId, faculty_id: facultyId, location_id: locationId,
      lec_type: lecType, sem_id: $('#popup-sem-id').val() || selectedSemId, class_id: classId, batch_id: batchId
    };

    if (slotId) {
      payload.id = slotId;
      $.ajax({
        url: 'update_slot.php',
        method: 'POST',
        dataType: 'json',
        data: payload,
        success: function(res){
          if (res && res.status === 'success') { showSuccess('Slot updated'); $('#slot_form').addClass('hidden'); loadTimetable(selectedSemId, selectedClassId); }
          else showError((res && res.message) ? res.message : 'Failed to update slot');
        },
        error: function(xhr,status,err){ console.error('update_slot',status,err,xhr.responseText); showError('Failed to update slot'); }
      });
    } else if (editIndex!=='') {
      slots[day][parseInt(editIndex)] = {
        start_time:startTime,end_time:endTime,subject_id:subjectId,faculty_id:facultyId,
        location_id:locationId,lec_type:lecType,classname:$('#class_batch option:selected').data('classname'),
        batch:$('#class_batch option:selected').data('batch'),subject_name:$('#subject option:selected').text(),faculty_name:$('#faculty option:selected').text(),
        saved:false
      };
      updateTiles(day);
      $('#slot_form').addClass('hidden');
      showSuccess('Slot updated (local)');
    } else {
      slots[day].push({
        start_time:startTime,end_time:endTime,subject_id:subjectId,faculty_id:facultyId,
        location_id:locationId,lec_type:lecType,classname:$('#class_batch option:selected').data('classname'),
        batch:$('#class_batch option:selected').data('batch'),subject_name:$('#subject option:selected').text(),faculty_name:$('#faculty option:selected').text(),
        saved:false
      });
      updateTiles(day);
      $('#slot_form').addClass('hidden');
      showSuccess('Slot added (local)');
    }
  });

  // Delete slot
  $(document).on('click', '.delete-slot', function(){
    const day = $(this).data('day');
    const idx = $(this).data('index');
    const slotId = $(this).data('slot-id');
    const isSaved = $(this).hasClass('saved-slot');

    Swal.fire({title:'Are you sure?',text:'Delete slot?',icon:'warning',showCancelButton:true}).then(r=>{
      if (!r.isConfirmed) return;
      if (isSaved && slotId) {
        $.post('delete_slot.php',{id:slotId}, function(res){
          if (res && res.status === 'success') { showSuccess('Slot deleted'); loadTimetable(selectedSemId, selectedClassId); }
          else showError((res && res.message) ? res.message : 'Failed to delete slot');
        }, 'json').fail(()=> showError('Failed to delete slot'));
      } else {
        slots[day].splice(idx,1);
        updateTiles(day);
        showSuccess('Slot removed');
      }
    });
  });

  // Update tiles UI
  function updateTiles(day) {
    const tiles = $('#tiles_'+day);
    tiles.empty();
    function formatTime(ts){ if(!ts) return ''; const [h,m]=ts.split(':'); const d=new Date(); d.setHours(parseInt(h),parseInt(m)); return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}); }

    const saved = (savedSlots[day]||[]).slice().sort((a,b)=>a.start_time.localeCompare(b.start_time));
    const unsaved = (slots[day]||[]).slice().sort((a,b)=>a.start_time.localeCompare(b.start_time));

    saved.forEach((s,i)=>{
      tiles.append(`<div class="tile">
        <span class="saved-tag saved">Saved</span>
        <div class="grid grid-cols-2 gap-4 text-left">
          <div>
            <p><strong>Time:</strong> ${formatTime(s.start_time)} - ${formatTime(s.end_time)}</p>
            <p><strong>Class:</strong> ${escapeHtml(s.classname)}${s.batch?(' - '+escapeHtml(s.batch).toUpperCase()):''}</p>
            <p><strong>Subject:</strong> ${escapeHtml(s.subject_name)} (${s.lec_type==='T'?'Theory':'Lab'})</p>
            <p><strong>Faculty:</strong> ${escapeHtml(s.faculty_name)}</p>
            <p><strong>Location:</strong> ${escapeHtml($('#class_location option[value="'+s.location_id+'"]').text())}</p>
          </div>
          <div class="flex items-end">
            <p>
              <button type="button" class="edit-slot saved-slot bg-cyan-600 text-white px-2 py-1 rounded-full" data-day="${day}" data-index="${i}">Edit</button>
              <button type="button" class="delete-slot saved-slot bg-red-500 text-white px-2 py-1 rounded-full ml-2" data-day="${day}" data-index="${i}" data-slot-id="${s.id}">Delete</button>
            </p>
          </div>
        </div>
      </div>`);
    });

    unsaved.forEach((s,i)=>{
      tiles.append(`<div class="tile">
        <span class="saved-tag not-saved">Not Saved</span>
        <div class="grid grid-cols-2 gap-4 text-left">
          <div>
            <p><strong>Time:</strong> ${formatTime(s.start_time)} - ${formatTime(s.end_time)}</p>
            <p><strong>Class:</strong> ${escapeHtml(s.classname)}${s.batch?(' - '+escapeHtml(s.batch).toUpperCase()):''}</p>
            <p><strong>Subject:</strong> ${escapeHtml(s.subject_name)} (${s.lec_type==='T'?'Theory':'Lab'})</p>
            <p><strong>Faculty:</strong> ${escapeHtml(s.faculty_name)}</p>
            <p><strong>Location:</strong> ${escapeHtml($('#class_location option[value="'+s.location_id+'"]').text())}</p>
          </div>
          <div class="flex items-end">
            <p>
              <button type="button" class="edit-slot bg-cyan-600 text-white px-2 py-1 rounded-full" data-day="${day}" data-index="${i}">Edit</button>
              <button type="button" class="delete-slot bg-red-500 text-white px-2 py-1 rounded-full ml-2" data-day="${day}" data-index="${i}">Delete</button>
            </p>
          </div>
        </div>
      </div>`);
    });
  }

  // Submit timetable: include batch_id (from popup or top-level batch-select) along with sem_id & class_id
  $('#submit_timetable').on('click', function(){
    if (!selectedSemId || !selectedClassId) return showError('Select semester & class first');
    Swal.fire({title:'Submit timetable?',icon:'question',showCancelButton:true}).then(r=>{
      if (!r.isConfirmed) return;
      const batchId = $('#batch_id').val() || $('#batch-select').val() || $('#class_batch option:selected').data('batch-id') || '';
      const payloadSlots = {};
      Object.keys(slots).forEach(d=>{
        payloadSlots[d] = (slots[d]||[]).map(s=>({
          start_time:s.start_time,end_time:s.end_time,
          subject_id:s.subject_id,faculty_id:s.faculty_id,
          location_id:s.location_id,lec_type:s.lec_type
        }));
      });
      $.ajax({
        url: 'save_timetable.php',
        method: 'POST',
        contentType: 'application/json; charset=utf-8', // ✅ send JSON properly
        dataType: 'json',
        data: JSON.stringify({
            sem_id: selectedSemId,
            class_id: selectedClassId,
            batch_id: batchId,
            slots: payloadSlots
        }),
        success: function(res) {
            if (res && res.status === 'success') {
            showSuccess('Timetable saved');
            loadTimetable(selectedSemId, selectedClassId);
            slots = { monday:[], tuesday:[], wednesday:[], thursday:[], friday:[] };
            } else {
            showError((res && res.message) ? res.message : 'Failed to save timetable');
            }
        },
        error: function(xhr, status, err) {
            console.error('save_timetable', status, err, xhr.responseText);
            showError('Failed to save timetable');
        }
        });
    });
  });

  // Subject change -> load faculty & configure lec_type
  $('#subject').on('change', function(){
    const sid = $(this).val();
    if (sid) {
      loadFaculty(sid, selectedClassId);
      configureLectureType($('#subject option:selected').data('lec-type'));
    } else {
      $('#faculty').html('<option value="" disabled selected>Select Faculty</option>').prop('disabled', true);
      $('#lec_type').val('').prop('disabled', false);
    }
  });

});
</script>
</body>
</html>