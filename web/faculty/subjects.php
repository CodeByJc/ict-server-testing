<?php
// Updated subjects page — shows Batch (start - end) column and asks batch list for the selected semester.
// Changes in this version:
// - The Add/Edit popup now includes a Batch dropdown (required when adding).
// - When editing, the popup shows the batch start and end year (read-only display).
// - Batch dropdown in the popup is populated whenever semester is selected (shared batchMap).
// - Selecting a batch in the popup updates the displayed start/end years.
//
// Backend endpoints expected:
// - fetch_batches.php (POST sem_id) -> returns JSON array of batches [{id,batch_start_year,batch_end_year,edu_type},...]
// - fetch_subjects_by_sem.php (POST sem_id [, batch_id]) -> returns JSON array of subject rows (must include batch_id)
// - add_subject.php, update_subject.php, delete_subject.php for CRUD actions

include('../../api/db/db_connection.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Subjects (with Batch)</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #subject-table { border-collapse: collapse; width: 100%; }
        #subject-table th, #subject-table td { text-align: center; border: 1px solid #d1d5db; padding: .5rem .75rem; }
        #subject-table th { background-color: #374151; color: #fff; }
        #subject-table tbody tr:hover { background-color: #f9fafb; }
        .action-btn { padding:6px 10px; border-radius:6px; cursor:pointer; background:#fff; }
        .muted { color: #6b7280; font-size: .9rem; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
    <?php $page_title = "Subjects"; include('./navbar.php'); ?>

    <div class="container mx-auto p-6">

        <div class="bg-white p-6 rounded-xl shadow-md mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="semester" class="block text-gray-700 font-bold mb-2">Semester & Program</label>
                <select id="semester" name="semester" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                    <option value="" disabled selected>Select Semester & Program</option>
                    <?php
                    $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
                    $sem_result = mysqli_query($conn, $sem_query);
                    if ($sem_result) {
                        while ($row = mysqli_fetch_assoc($sem_result)) {
                            echo "<option value='" . htmlspecialchars($row['id'], ENT_QUOTES) . "'>SEM " . htmlspecialchars($row['sem'], ENT_QUOTES) . " - " . strtoupper(htmlspecialchars($row['edu_type'], ENT_QUOTES)) . "</option>";
                        }
                    } else {
                        echo "<option value='' disabled>Unable to load semesters</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="batch" class="block text-gray-700 font-bold mb-2">Batch</label>
                <select id="batch" name="batch" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                    <option value="">All Batches</option>
                    <!-- populated dynamically -->
                </select>
            </div>

            <div class="flex gap-2 justify-end">
                <button id="refresh-subjects" class="bg-cyan-500 text-white px-4 py-2 rounded">Refresh</button>
                <button id="open-create" class="bg-green-500 text-white px-4 py-2 rounded">Add Subject</button>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <table id="subject-table" aria-describedby="subject-list">
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th style="width:20%;">Subject Name</th>
                        <th style="width:10%;">Short Name</th>
                        <th style="width:10%;">Subject Code</th>
                        <th style="width:10%;">Subject Type</th>
                        <th style="width:10%;">Lecture Type</th>
                        <th style="width:10%;">Credit Type</th>
                        <th style="width:12%;">Batch (Start - End)</th>
                        <th style="width:14%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="subject-tbody">
                    <tr><td colspan="9" class="p-4 text-sm text-gray-500">Please select a semester to view subjects.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Popup -->
<div id="popup-modal" class="fixed inset-0 bg-gray-800 bg-opacity-50 hidden flex justify-center items-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <h2 id="popup-title" class="text-xl font-bold mb-4">Add Subject</h2>
        <form id="popup-form" method="POST">
            <input type="hidden" name="subject_id" id="subject_id">
            <input type="hidden" name="sem_info_id" id="sem_info_id">
            <!-- We will also include visible select for batch -->
            <div class="mb-4">
                <label for="popup-batch-select" class="block text-sm font-medium mb-1">Batch</label>
                <select id="popup-batch-select" name="batch_id" class="border-2 rounded p-2 w-full" required>
                    <option value="">Please select a batch</option>
                    <!-- filled dynamically from batchMap -->
                </select>
                <div id="popup-batch-years" class="muted mt-1"></div>
            </div>

            <div class="mb-4">
                <label for="subject_name" class="block text-sm font-medium mb-1">Subject Name</label>
                <input type="text" id="subject_name" name="subject_name" class="border-2 rounded p-2 w-full" required>
            </div>

            <div class="mb-4">
                <label for="short_name" class="block text-sm font-medium mb-1">Short Name (Uppercase)</label>
                <input type="text" id="short_name" name="short_name" class="border-2 rounded p-2 w-full" oninput="this.value = this.value.toUpperCase();" required>
            </div>

            <div class="mb-4">
                <label for="subject_code" class="block text-sm font-medium mb-1">Subject Code</label>
                <input type="text" id="subject_code" name="subject_code" class="border-2 rounded p-2 w-full" required>
            </div>

            <div class="mb-4">
                <label for="subject_type" class="block text-sm font-medium mb-1">Subject Type</label>
                <select id="subject_type" name="subject_type" class="border-2 rounded p-2 w-full" required>
                    <option value="" disabled selected>Select Subject Type</option>
                    <option value="mandatory">Mandatory</option>
                    <option value="elective">Elective</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="lec_type" class="block text-sm font-medium mb-1">Lecture Type</label>
                <select id="lec_type" name="lec_type" class="border-2 rounded p-2 w-full" required>
                    <option value="" disabled selected>Select Lecture Type</option>
                    <option value="L">Lab only</option>
                    <option value="T">Theory only</option>
                    <option value="LT">Lab and Theory</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="is_creditable" class="block text-sm font-medium mb-1">Credit Type</label>
                <select id="is_creditable" name="is_creditable" class="border-2 rounded p-2 w-full" required>
                    <option value="" disabled selected>Select Credit Type</option>
                    <option value="1">Credit</option>
                    <option value="0">Non-Credit</option>
                </select>
            </div>

            <div class="flex justify-end gap-4">
                <button type="button" onclick="closePopup()" class="pl-5 pr-5 bg-gray-500 hover:bg-gray-600 text-white p-2 rounded-full">Cancel</button>
                <button type="submit" id="popup-submit" class="pl-6 pr-6 bg-cyan-500 hover:bg-cyan-600 text-white p-2 rounded-full">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
$(function () {
    let batchMap = {}; // batch_id -> {start_year, end_year, label}
    const $semester = $('#semester');
    const $batch = $('#batch');
    const $tbody = $('#subject-tbody');
    const $popupBatchSelect = $('#popup-batch-select');
    const $popupBatchYears = $('#popup-batch-years');

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function renderSubjects(subjects) {
        if (!Array.isArray(subjects) || subjects.length === 0) {
            $tbody.html('<tr><td colspan="9" class="p-4 text-sm text-gray-500">No subjects found.</td></tr>');
            return;
        }
        let html = '';
        subjects.forEach((sub, idx) => {
            const name = escapeHtml(sub.subject_name || 'N/A');
            const shortName = escapeHtml(sub.short_name || 'N/A');
            const code = escapeHtml(sub.subject_code || 'N/A');
            const type = escapeHtml(sub.type || '');
            const lecType = sub.lec_type === 'L' ? 'Lab only' : (sub.lec_type === 'T' ? 'Theory only' : (sub.lec_type === 'LT' ? 'Lab and Theory' : escapeHtml(sub.lec_type || '')));
            const isCreditRaw = sub.iscredit ?? sub.is_creditable ?? sub.is_credit ?? sub.isCreditable ?? sub.is_creditable;
            const credit = (isCreditRaw === 1 || isCreditRaw === '1' || isCreditRaw === true || isCreditRaw === 'true') ? 'Credit' : 'Non-Credit';
            const batchId = sub.batch_id ?? sub.batchId ?? sub.batch_id;
            const batchLabel = (batchId && batchMap[batchId]) ? escapeHtml(batchMap[batchId].label) : '';
            const editBtn = `<button class="action-btn edit-btn border border-blue-600 text-blue-600 mr-2" 
                                data-id="${escapeHtml(sub.id)}"
                                data-name="${escapeHtml(sub.subject_name)}"
                                data-short="${escapeHtml(sub.short_name)}"
                                data-code="${escapeHtml(sub.subject_code)}"
                                data-type="${escapeHtml(sub.type)}"
                                data-lec="${escapeHtml(sub.lec_type)}"
                                data-credit="${escapeHtml(isCreditRaw)}"
                                data-sem="${escapeHtml(sub.sem_info_id)}"
                                data-batch="${escapeHtml(batchId)}"
                              >Edit</button>`;
            const delBtn = `<button class="action-btn delete-btn border border-red-600 text-red-600" data-id="${escapeHtml(sub.id)}">Delete</button>`;

            html += `<tr>
                <td>${idx+1}</td>
                <td>${name}</td>
                <td>${shortName}</td>
                <td>${code}</td>
                <td>${escapeHtml(type)}</td>
                <td>${escapeHtml(lecType)}</td>
                <td>${escapeHtml(credit)}</td>
                <td>${batchLabel}</td>
                <td>${editBtn}${delBtn}</td>
            </tr>`;
        });
        $tbody.html(html);
    }

    function populateBatchSelects(batches) {
        // populate both main batch filter and popup batch select
        $batch.html('<option value="">All Batches</option>');
        $popupBatchSelect.html('<option value="">Please select a batch</option>');
        batchMap = {};
        batches.forEach(b => {
            const label = `${b.batch_start_year}-${b.batch_end_year}-${b.edu_type.toUpperCase()}`;
            batchMap[b.id] = { start: b.batch_start_year, end: b.batch_end_year, label };
            $batch.append(`<option value="${escapeHtml(b.id)}">${escapeHtml(label)}</option>`);
            $popupBatchSelect.append(`<option value="${escapeHtml(b.id)}">${escapeHtml(label)}</option>`);
        });
    }

    function loadBatchesForSemester(semId) {
        batchMap = {};
        $batch.html('<option value="">All Batches</option>');
        $popupBatchSelect.html('<option value="">Please select a batch</option>');
        $popupBatchYears.text('');
        if (!semId) return $.Deferred().resolve().promise();
        return $.ajax({
            url: 'fetch_batches.php',
            method: 'POST',
            dataType: 'json',
            data: { sem_id: semId }
        }).done(function (batches) {
            if (!Array.isArray(batches)) {
                console.error('Invalid batches response', batches);
                return;
            }
            populateBatchSelects(batches);
        }).fail(function(xhr){
            console.error('fetch_batches error', xhr.responseText);
            Swal.fire('Error', 'Failed to load batches. See console for details.', 'error');
        });
    }

    function loadSubjects() {
        const sem_id = $semester.val();
        const batch_id = $batch.val() || '';
        if (!sem_id) {
            $tbody.html('<tr><td colspan="9" class="p-4 text-sm text-gray-500">Please select a semester to view subjects.</td></tr>');
            return;
        }
        $.ajax({
            url: 'fetch_subjects_by_sem.php',
            method: 'POST',
            dataType: 'json',
            data: { sem_id: sem_id, batch_id: batch_id },
            success: function (data) {
                if (data && Array.isArray(data)) {
                    renderSubjects(data);
                } else if (data && data.error) {
                    $tbody.html(`<tr><td colspan="9" class="p-4 text-sm text-red-500">${escapeHtml(data.error)}</td></tr>`);
                } else {
                    console.error('Unexpected response from fetch_subjects_by_sem.php', data);
                    $tbody.html('<tr><td colspan="9" class="p-4 text-sm text-red-500">Failed to load subjects (unexpected response).</td></tr>');
                }
            },
            error: function (xhr, status, err) {
                console.error('fetch_subjects_by_sem.php error', status, err, xhr.responseText);
                $tbody.html('<tr><td colspan="9" class="p-4 text-sm text-red-500">Failed to load subjects (server error).</td></tr>');
            }
        });
    }

    // When semester changes: load batches then subjects
    $semester.on('change', function () {
        const semId = $(this).val();
        loadBatchesForSemester(semId).always(function () {
            loadSubjects();
        });
    });

    // When batch changes: reload subjects
    $batch.on('change', function () {
        loadSubjects();
    });

    // When popup batch select changes: show start/end years
    $popupBatchSelect.on('change', function () {
        const batchId = $(this).val();
        if (batchId && batchMap[batchId]) {
            $popupBatchYears.text(`Batch: ${batchMap[batchId].start} - ${batchMap[batchId].end}`);
        } else {
            $popupBatchYears.text('');
        }
    });

    // Refresh button
    $('#refresh-subjects').on('click', function(){ 
        loadBatchesForSemester($semester.val()).always(loadSubjects);
    });

    // Add button
    $('#open-create').on('click', function () {
        $('#popup-title').text('Add Subject');
        $('#subject_id').val('');
        $('#sem_info_id').val($semester.val() || '');
        // set popup batch select to currently selected batch if any
        const currentBatch = $batch.val() || '';
        $('#popup-batch-select').val(currentBatch);
        $popupBatchSelect.trigger('change');
        $('#popup-modal').removeClass('hidden');
    });

    // Edit delegated
    $(document).on('click', '.edit-btn', function () {
        const $b = $(this);
        const id = $b.data('id');
        const name = $b.data('name') || '';
        const shortName = $b.data('short') || '';
        const code = $b.data('code') || '';
        const type = $b.data('type') || '';
        const lec = $b.data('lec') || '';
        const credit = $b.data('credit') ?? '';
        const sem = $b.data('sem') || $semester.val() || '';
        const batch = $b.data('batch') || $batch.val() || '';

        $('#popup-title').text('Edit Subject');
        $('#subject_id').val(id);
        $('#sem_info_id').val(sem);
        $('#batch_id').val(batch);
        $('#subject_name').val(name);
        $('#short_name').val(shortName);
        $('#subject_code').val(code);
        $('#subject_type').val(type);
        $('#lec_type').val(lec);
        const c = (credit === 1 || credit === '1' || credit === true || credit === 'true') ? '1' : (credit === 0 || credit === '0' ? '0' : '');
        $('#is_creditable').val(c);

        // Ensure popup batch select is populated (if not already)
        if ($('#popup-batch-select option').length <= 1 && $semester.val()) {
            // reload batches synchronously-ish then set value
            loadBatchesForSemester($semester.val()).always(function() {
                $('#popup-batch-select').val(batch);
                $popupBatchSelect.trigger('change');
            });
        } else {
            $('#popup-batch-select').val(batch);
            $popupBatchSelect.trigger('change');
        }

        $('#popup-modal').removeClass('hidden');
    });

    // Delete delegated
    $(document).on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: 'This subject will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'delete_subject.php',
                method: 'POST',
                dataType: 'json',
                data: { id },
                success(resp) {
                    if (resp && resp.status === 'success') {
                        Swal.fire('Deleted', resp.message || 'Subject deleted', 'success');
                        loadSubjects();
                    } else {
                        Swal.fire('Error', resp?.message || 'Delete failed', 'error');
                    }
                },
                error(xhr) {
                    console.error('delete_subject error', xhr.responseText);
                    Swal.fire('Error', 'Delete failed. Check console for details.', 'error');
                }
            });
        });
    });

    // Submit add/update
    $('#popup-form').on('submit', function (e) {
        e.preventDefault();
        const subId = $('#subject_id').val();
        const url = subId ? 'C.php' : 'add_subject.php';
        const payload = $(this).serializeArray();
        // ensure sem_info_id and batch_id are present
        if (!payload.find(p => p.name === 'sem_info_id')) payload.push({ name: 'sem_info_id', value: $('#sem_info_id').val() || $semester.val() || '' });
        if (!payload.find(p => p.name === 'batch_id')) payload.push({ name: 'batch_id', value: $('#popup-batch-select').val() || $batch.val() || '' });

        // require batch when adding
        if (!subId && (!$('#popup-batch-select').val())) {
            Swal.fire('Validation', 'Please select a batch for the subject.', 'warning');
            return;
        }

        $.ajax({
            url,
            method: 'POST',
            dataType: 'json',
            data: payload,
            success(resp) {
                if (resp && resp.status === 'success') {
                    Swal.fire('Saved', resp.message || 'Saved successfully', 'success');
                    closePopup();
                    // reload batches & subjects to reflect changes
                    loadBatchesForSemester($semester.val()).always(loadSubjects);
                } else {
                    Swal.fire('Error', resp?.message || 'Save failed', 'error');
                }
            },
            error(xhr) {
                console.error('save error', xhr.responseText);
                Swal.fire('Error', 'Save failed (see console)', 'error');
            }
        });
    });

    // initial trigger if semester already selected
    if ($semester.val()) {
        loadBatchesForSemester($semester.val()).always(loadSubjects);
    }
});

function closePopup() {
    $('#popup-modal').addClass('hidden');
    $('#popup-form')[0].reset();
    $('#subject_id').val('');
    $('#sem_info_id').val('');
    $('#batch_id').val('');
    $('#popup-batch-select').val('');
    $('#popup-batch-years').text('');
}
</script>
</body>
</html>