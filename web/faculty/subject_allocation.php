<?php
// allocate_faculty.php
// UI + AJAX endpoints to allocate faculty to assigned subjects (subject_allocation table).
// Uses ../../api/db/db_connection.php for DB connection and keeps your Tailwind/SweetAlert theme.

include('../../api/db/db_connection.php');
if (!$conn) {
    http_response_code(500);
    die('Database connection failed');
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // Return subjects for the semester (derived from class)
    if ($action === 'fetch_subjects') {
        $sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;
        if ($sem_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid semester id']);
            exit;
        }

        $sql = "SELECT id, subject_name, short_name, subject_code, lec_type, CAST(is_creditable AS UNSIGNED) AS is_creditable
                FROM subject_info WHERE sem_info_id = ? and type = 'mandatory' ORDER BY subject_name";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'DB prepare error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('i', $sem_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $r['is_creditable'] = isset($r['is_creditable']) ? (int)$r['is_creditable'] : 0;
            $rows[] = $r;
        }
        $stmt->close();
        echo json_encode(['status' => 'ok', 'data' => $rows]);
        exit;
    }

    // Return faculties (use prepared statement for consistency)
    if ($action === 'fetch_faculties') {
        $sql = "SELECT f.id,
                       COALESCE(CONCAT(TRIM(f.first_name), ' ', TRIM(f.last_name)), COALESCE(ul.username, ''), '') AS name
                FROM faculty_info f
                LEFT JOIN user_login ul ON f.user_login_id = ul.username
                ORDER BY f.first_name, f.last_name, ul.username";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'DB prepare error: ' . $conn->error]);
            exit;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        echo json_encode(['status' => 'ok', 'data' => $rows]);
        exit;
    }

    // Get allocations for a class (subject_allocation)
    if ($action === 'get_allocations') {
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        if ($class_id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid class id']); exit; }

        $sql = "SELECT subject_info_id AS subject_id, faculty_info_id AS faculty_id FROM subject_allocation WHERE class_info_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['status' => 'error', 'message' => $conn->error]); exit; }
        $stmt->bind_param('i', $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $alloc = [];
        while ($r = $res->fetch_assoc()) $alloc[] = ['subject_id' => (int)$r['subject_id'], 'faculty_id' => (int)$r['faculty_id']];
        $stmt->close();
        echo json_encode(['status' => 'ok', 'data' => $alloc]);
        exit;
    }

    // Save allocations for a class: assignments JSON [{subject_id, faculty_id}, ...]
    if ($action === 'save_allocations') {
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $assignments_json = isset($_POST['assignments']) ? $_POST['assignments'] : '[]';
        if ($class_id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid class id']); exit; }

        $assignments = json_decode($assignments_json, true);
        if (!is_array($assignments)) $assignments = [];

        // sanitize & normalize assignments: allow multiple faculty per subject
        $clean = [];
        foreach ($assignments as $a) {
            $sid = isset($a['subject_id']) ? intval($a['subject_id']) : 0;
            $fid = isset($a['faculty_id']) ? intval($a['faculty_id']) : 0;
            if ($sid > 0) $clean[] = ['subject_id' => $sid, 'faculty_id' => $fid];
        }

        // Use transaction: delete existing then insert new rows (one row per subject+faculty)
        $conn->begin_transaction();
        try {
            $del = $conn->prepare("DELETE FROM subject_allocation WHERE class_info_id = ?");
            if (!$del) throw new Exception('Prepare delete failed: ' . $conn->error);
            $del->bind_param('i', $class_id);
            $del->execute();
            $del->close();

            if (!empty($clean)) {
                $ins = $conn->prepare("INSERT INTO subject_allocation (faculty_info_id, subject_info_id, class_info_id) VALUES (?, ?, ?)");
                if (!$ins) throw new Exception('Prepare insert failed: ' . $conn->error);
                foreach ($clean as $row) {
                    $fid = $row['faculty_id'];
                    $sid = $row['subject_id'];
                    $ins->bind_param('iii', $fid, $sid, $class_id);
                    $ins->execute();
                }
                $ins->close();
            }

            $conn->commit();
            echo json_encode(['status' => 'ok', 'message' => 'Allocations saved']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Save failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Allocate Faculty to Subjects</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <style>
        /* clean & focused, matching your app theme */
        .card { background:#fff; border-radius:12px; padding:18px; box-shadow: 0 6px 18px rgba(15,23,42,0.06); border:1px solid #e6edf3; }
        .subject-list { max-height:520px; overflow:auto; border:1px solid #e6edf3; border-radius:8px; background:#fff; }
        .small { font-size:0.9rem; color:#6b7280; }
        .faculty-select { min-width:200px; }
        select[multiple] { min-height:96px; }
        .hint { font-size:0.85rem; color:#4b5563; }
        .faculty-list {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            background: #fafafa;
            max-height: 140px;
            overflow-y: auto;
        }
        .faculty-list label:hover {
            background-color: #f1f5f9;
            border-radius: 6px;
        }

    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
    <?php $page_title = "Allocate Faculty"; include('./navbar.php'); ?>

    <div class="container mx-auto p-6">
        <div class="card mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Class</label>
                    <select id="class_select" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <option value="" disabled selected>Select Class</option>
                        <?php
                        $sql = "SELECT c.id, c.classname, c.batch, c.sem_info_id, s.sem, s.edu_type
                                FROM class_info c
                                LEFT JOIN sem_info s ON s.id = c.sem_info_id
                                ORDER BY s.edu_type, s.sem, c.classname, c.batch";
                        $res = mysqli_query($conn, $sql);
                        while ($r = mysqli_fetch_assoc($res)) {
                            $label = sprintf('%s (Batch %s) - SEM %s - %s', $r['classname'], $r['batch'], $r['sem'] ?? 'N/A', strtoupper($r['edu_type'] ?? ''));
                            echo '<option value="'.h($r['id']).'" data-sem="'.h($r['sem_info_id']).'">'.h($label).'</option>';
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">Semester</label>
                    <input id="sem_display" readonly class="w-full p-3 border-2 rounded-xl bg-gray-50" />
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">Actions</label>
                    <div class="flex gap-2">
                        <button id="btn_refresh" class="bg-white border px-4 py-2 rounded-full hover:shadow">Refresh</button>
                        <button id="btn_save" class="ml-auto bg-cyan-500 text-white px-4 py-2 rounded-full shadow-md hover:bg-cyan-600" disabled>Save Allocations</button>
                    </div>
                    <p class="small mt-2">Check subjects then select one or more faculty per subject. To select multiple faculty hold Ctrl (Windows) or Cmd (Mac) while clicking.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="text-lg font-semibold mb-3">Allocate Faculty to Assigned Subjects</h2>

            <div class="flex gap-6">
                <div class="w-2/3">
                    <div id="subjects_container" class="subject-list p-2">
                        <div id="no_subjects" class="small p-4">Select a class to load its semester subjects.</div>
                        <div id="subjects_box"></div>
                    </div>
                </div>

                <div class="w-1/3">
                    <div class="p-4 border rounded">
                        <h3 class="font-semibold">Preview & Summary</h3>
                        <div id="assigned_count" class="mt-3 small">Assigned: 0</div>
                        <div id="assigned_preview" class="mt-3 small">No allocations yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    let currentClassId = null;
    let currentSemId = null;
    let subjectsCache = [];
    let facultyCache = [];

    function toastError(msg){ Swal.fire({icon:'error', title:'Error', text: msg}); }
    function toastSuccess(msg){ Swal.fire({icon:'success', title:'Success', text: msg, timer:1200, showConfirmButton:false}); }
    function escapeHtml(text){ if (text === null || text === undefined) return ''; return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

    // Load faculties once
    function loadFaculties(cb) {
        if (facultyCache.length) { if (cb) cb(); return; }
        $.post('', { action: 'fetch_faculties' }, function(resp){
            if (!resp || resp.status !== 'ok') { toastError('Failed to load faculties'); if (cb) cb(); return; }
            facultyCache = resp.data || [];
            if (cb) cb();
        }, 'json').fail(function(xhr){ toastError('AJAX error loading faculties: ' + xhr.responseText); if (cb) cb(); });
    }

    // Load subjects for semester
    function loadSubjectsForSem(sem_id) {
        $('#subjects_box').empty();
        $('#no_subjects').show().text('Loading subjects...');
        $('#btn_save').prop('disabled', true);
        $('#assigned_preview').text('Loading...');
        $.post('', { action: 'fetch_subjects', sem_id: sem_id }, function(resp){
            if (!resp || resp.status !== 'ok') {
                toastError((resp && resp.message) ? resp.message : 'Failed to fetch subjects');
                $('#no_subjects').text('Failed to load subjects.');
                return;
            }
            subjectsCache = resp.data || [];
            if (subjectsCache.length === 0) {
                $('#no_subjects').text('No subjects found for this semester.');
                $('#assigned_preview').text('—');
                $('#assigned_count').text('Assigned: 0');
                return;
            }
            $('#no_subjects').hide();

            loadFaculties(function(){
                const $box = $('#subjects_box');
                subjectsCache.forEach(function(s){
                    const id = s.id;
                    const creditLabel = (s.is_creditable == 1 || s.is_creditable === '1') ? 'Credit' : 'Non-Credit';
                    // multi-select for faculty (allow multiple selection)
                    let facultyOptions = '<option value="0">Unassigned</option>';
                    facultyCache.forEach(function(f){ facultyOptions += `<option value="${escapeHtml(f.id)}">${escapeHtml(f.name)}</option>`; });

                    const row = `<div class="flex items-center justify-between py-3 border-b last:border-b-0">
                        <label class="flex items-center gap-3 w-full">
                            <input type="checkbox" class="alloc-checkbox" data-sub="${escapeHtml(id)}">
                            <div class="flex-1">
                                <div class="font-medium">${escapeHtml(s.subject_name)}</div>
                                <div class="small text-gray-500">${escapeHtml(s.short_name)} · ${escapeHtml(s.subject_code)}</div>
                            </div>
                            <div class="small text-gray-600 pr-2">${escapeHtml(creditLabel)}</div>
                            <div>
                                <div class="faculty-list space-y-1" data-subject="${escapeHtml(id)}">
                                    ${facultyCache.map(f => `
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" class="faculty-checkbox accent-cyan-500" value="${escapeHtml(f.id)}" data-subject="${escapeHtml(id)}">
                                            <span>${escapeHtml(f.name)}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                        </label>
                    </div>`;
                    $box.append(row);
                });

                // fetch existing allocations for this class
                if (currentClassId) fetchAllocations(currentClassId);
                $('#btn_save').prop('disabled', false);
            });
        }, 'json').fail(function(xhr){ toastError('AJAX error when loading subjects: ' + xhr.responseText); $('#no_subjects').text('Failed to load subjects.'); });
    }

    // Fetch allocations for class and set UI
    function fetchAllocations(class_id) {
        $.post('', { action: 'get_allocations', class_id: class_id }, function(resp){
            if (!resp || resp.status !== 'ok') { toastError((resp && resp.message) ? resp.message : 'Failed to fetch allocations'); return; }
            const alloc = resp.data || [];

            // Build mapping subject_id => [faculty_ids]
            const map = {};
            alloc.forEach(function(a){
                if (!map[a.subject_id]) map[a.subject_id] = [];
                map[a.subject_id].push(String(a.faculty_id));
            });

            $('.alloc-checkbox').prop('checked', false);
            $('.faculty-checkbox').prop('checked', false);
            Object.keys(map).forEach(function(sid){
                const arr = map[sid];
                const filtered = arr.filter(x => x !== null && x !== undefined);
                $(`.alloc-checkbox[data-sub="${sid}"]`).prop('checked', filtered.length > 0);
                $(`.faculty-select[data-subject="${sid}"]`).val(filtered);
                filtered.forEach(function(fid){
                    $(`.faculty-checkbox[data-subject="${sid}"][value="${fid}"]`).prop('checked', true);
                });
            });

            // Update preview and count
            const uniqueSubjects = Object.keys(map).length;
            let totalRows = 0;
            Object.values(map).forEach(v => { totalRows += v.length; });
            $('#assigned_count').text('Assigned subjects: ' + uniqueSubjects + ' — total faculty rows: ' + totalRows);

            const names = [];
            Object.keys(map).forEach(function(sid){
                const subject = subjectsCache.find(x => parseInt(x.id) === parseInt(sid));
                const facNames = (map[sid] || []).map(function(fid){
                    const f = facultyCache.find(x => parseInt(x.id) === parseInt(fid));
                    return f ? f.name : ('#' + fid);
                });
                names.push((subject ? subject.subject_name : ('#' + sid)) + ' (' + (facNames.length ? facNames.join(', ') : 'Unassigned') + ')');
            });
            $('#assigned_preview').html(names.length ? names.join('<br>') : 'No assigned subjects');
        }, 'json').fail(function(xhr){ toastError('AJAX error fetching allocations: ' + xhr.responseText); });
    }

    // Save allocations
    function saveAllocations(class_id) {
        const assignments = [];
        $('#subjects_box .alloc-checkbox').each(function(){
            const sid = $(this).data('sub');
            const checked = $(this).is(':checked');
            // get selected options from multi-select
            const raw = [];
            $(`.faculty-checkbox[data-subject="${sid}"]:checked`).each(function(){
                raw.push($(this).val());
            });

            let arr = Array.isArray(raw) ? raw.slice() : [raw];
            arr = arr.filter(x => x !== null && x !== undefined && x !== '');
            if (arr.length > 1) arr = arr.filter(x => x !== '0'); // remove Unassigned if other selections exist
            if (checked) {
                if (arr.length === 0) {
                    assignments.push({ subject_id: parseInt(sid), faculty_id: 0 });
                } else {
                    arr.forEach(function(fid){
                        assignments.push({ subject_id: parseInt(sid), faculty_id: parseInt(fid) || 0 });
                    });
                }
            }
        });

        $.post('', { action: 'save_allocations', class_id: class_id, assignments: JSON.stringify(assignments) }, function(resp){
            if (!resp) { toastError('Invalid server response'); return; }
            if (resp.status !== 'ok') return toastError(resp.message || 'Save failed');
            toastSuccess(resp.message || 'Allocations saved');
            fetchAllocations(class_id);
        }, 'json').fail(function(xhr){ toastError('AJAX error on save: ' + xhr.responseText); });
    }

    // UI handlers
    $('#class_select').on('change', function(){
        currentClassId = $(this).val() ? parseInt($(this).val()) : null;
        currentSemId = $(this).find(':selected').data('sem') ? parseInt($(this).find(':selected').data('sem')) : null;
        $('#sem_display').val(currentSemId ? 'SEM ' + currentSemId : '');
        if (currentClassId && currentSemId) {
            loadSubjectsForSem(currentSemId);
            $('#btn_save').prop('disabled', false);
        } else {
            $('#subjects_box').empty();
            $('#no_subjects').show().text('Select a class to load its semester subjects.');
            $('#assigned_preview').text('No allocations yet.');
            $('#assigned_count').text('Assigned: 0');
            $('#btn_save').prop('disabled', true);
        }
    });

    $('#btn_refresh').on('click', function(){ if (currentSemId) loadSubjectsForSem(currentSemId); else toastError('Select a class first'); });
    $('#btn_save').on('click', function(){ if (!currentClassId) return toastError('Select a class first'); saveAllocations(currentClassId); });

    // Initial state
    loadFaculties();
    $('#class_select').trigger('change');
});
</script>
</body>
</html>