<?php
// result_batch_class_view_v2.php
// Enhanced single-file UI + AJAX endpoints for viewing results batch-wise or class+batch-wise.
// - Improved controls: Mode (Batch / Class+Batch), Semester (including All -> 0), Batch, Class (loads when needed)
// - Column toggles, Select all students, Export selected to CSV (client-side), Search, Pagination (simple client-side)
// - Table area is independently scrollable with sticky header (tabular, designer-friendly)
// - AJAX endpoints:
//     GET  ?action=get_classes&batch_id=...
//     POST ?action=fetch_results (params: sem_info_id, batch_info_id, class_info_id)
// - Uses prepared statements (mysqli). Expects db_connection.php to provide $conn (mysqli).
//
// IMPORTANT: adjust the require_once path below if your db_connection.php lives elsewhere.

include('../../api/db/db_connection.php');// <- correct relative include

header('Content-Type: text/html; charset=utf-8');

// ----------------- AJAX endpoints -----------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'get_classes') {
        $batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
        echo json_encode(['status' => true, 'classes' => fetch_classes_for_batch($batch_id)]);
        exit;
    }
    echo json_encode(['status' => false, 'message' => 'Unknown action']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_results') {
    $sem_info_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
    $batch_info_id = isset($_POST['batch_info_id']) ? intval($_POST['batch_info_id']) : 0;
    $class_info_id = isset($_POST['class_info_id']) ? intval($_POST['class_info_id']) : 0;

    $res = fetch_results($sem_info_id, $batch_info_id, $class_info_id);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res);
    exit;
}

// ----------------- Page (GET) -----------------
$sems = fetch_all_sem_info();
$batches = fetch_all_batches();
$firstBatchId = !empty($batches) ? intval($batches[0]['id']) : 0;
$classesForFirstBatch = $firstBatchId ? fetch_classes_for_batch($firstBatchId) : [];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Results — Batch / Class View (v2)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --sidebar-w: 240px;
      --gap: 14px;
      --card-radius: 10px;
    }
    body { background:#f3f6fb; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .layout {
      display: grid;
      grid-template-columns: var(--sidebar-w) 1fr;
      gap: var(--gap);
      padding: 18px;
      min-height: 100vh;
      box-sizing: border-box;
    }
    .sidebar {
      background: #fff;
      border-radius: var(--card-radius);
      padding: 16px;
      box-shadow: 0 4px 14px rgba(16,24,40,0.06);
      overflow: auto;
    }
    .main {
      display:flex;
      flex-direction:column;
      gap:12px;
      min-height:0;
    }
    .controls {
      display:flex;
      gap:12px;
      align-items:center;
      flex-wrap:wrap;
    }
    .control-card { background:#fff; border-radius:8px; padding:10px; box-shadow:0 2px 6px rgba(16,24,40,0.04); }
    .results-card { background:#fff; border-radius:10px; padding:8px; flex:1 1 auto; box-shadow: 0 4px 18px rgba(16,24,40,0.06); display:flex; flex-direction:column; min-height:0; }
    .table-area { flex:1 1 auto; overflow:auto; border-radius:8px; border:1px solid #eef1f6; background:#fff; }
    table.results-table { width:100%; border-collapse:collapse; }
    table.results-table thead th { position: sticky; top:0; background:#fafbfd; z-index:2; border-bottom:1px solid #e6eaf0; padding:12px; text-align:left; font-weight:600; }
    table.results-table tbody td { padding:12px; border-bottom:1px solid #f1f3f6; vertical-align:top; }
    .student-row { background:linear-gradient(180deg, #ffffff, #fbfdff); }
    .muted { color:#6c757d; }
    .chip { display:inline-block; padding:6px 10px; border-radius:999px; background:#f1f5f9; margin-right:8px; font-weight:600; }
    .sticky-right { position: sticky; right:0; background:#fff; }
    @media (max-width:900px) {
      .layout { grid-template-columns: 1fr; padding: 12px; }
      .sidebar { order:2; }
      .main { order:1; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <h5 class="mb-3">Result Filters</h5>

      <div class="mb-3">
        <label class="form-label small">Mode</label>
        <div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="mode" id="mode_batch" value="batch" checked>
            <label class="form-check-label small" for="mode_batch">Batch wise</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="mode" id="mode_class" value="class">
            <label class="form-check-label small" for="mode_class">Class & Batch wise</label>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small">Semester</label>
        <select id="sem_select" class="form-select form-select-sm">
          <option value="0">All Semesters</option>
          <?php foreach ($sems as $s): ?>
            <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['edu_type'] . ' - Sem ' . $s['sem']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text small">Choose semester (0 → all)</div>
      </div>

      <div class="mb-3">
        <label class="form-label small">Batch (branch)</label>
        <select id="batch_select" class="form-select form-select-sm">
          <option value="0">-- Select Batch --</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?= htmlspecialchars($b['id']) ?>"><?= htmlspecialchars($b['batch_start_year'] . ' - ' . $b['batch_end_year']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3" id="class_block" style="display:none;">
        <label class="form-label small">Class</label>
        <select id="class_select" class="form-select form-select-sm">
          <option value="0">-- Select Class --</option>
          <?php foreach ($classesForFirstBatch as $c): ?>
            <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['classname']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="d-grid">
        <button id="view_btn" class="btn btn-primary btn-sm">View Results</button>
      </div>

      <hr>
      <div class="small text-muted">
        Tip: Use "All Semesters" to apply the 0 logic. Select "Class & Batch" to filter by class too.
      </div>
    </aside>

    <main class="main">
      <div class="controls">
        <div class="control-card">
          <strong id="summary_count">Results</strong>
          <div class="small muted" id="summary_info">No data loaded</div>
        </div>

        <div class="control-card">
          <label class="form-label small mb-1">Columns</label>
          <div>
            <div class="form-check form-check-inline">
              <input id="col_student" class="form-check-input col-toggle" type="checkbox" checked>
              <label class="form-check-label small">Student</label>
            </div>
            <div class="form-check form-check-inline">
              <input id="col_sem" class="form-check-input col-toggle" type="checkbox" checked>
              <label class="form-check-label small">Sem</label>
            </div>
            <div class="form-check form-check-inline">
              <input id="col_cgpa" class="form-check-input col-toggle" type="checkbox" checked>
              <label class="form-check-label small">CGPA/SGPA</label>
            </div>
            <div class="form-check form-check-inline">
              <input id="col_backlog" class="form-check-input col-toggle" type="checkbox" checked>
              <label class="form-check-label small">Backlog</label>
            </div>
          </div>
        </div>

        <div class="control-card ms-auto">
          <label class="form-label small mb-1">Search</label>
          <input type="search" id="search_input" class="form-control form-control-sm" placeholder="Enrollment | GR | Name">
        </div>

        <div class="control-card">
          <label class="form-label small mb-1">Actions</label>
          <div class="d-flex gap-2">
            <button id="export_csv" class="btn btn-outline-secondary btn-sm">Export CSV</button>
            <button id="export_pdf" class="btn btn-outline-secondary btn-sm">Export PDF</button>
          </div>
        </div>
      </div>

      <div class="results-card">
        <div style="padding:8px 12px">
          <div class="d-flex align-items-center gap-3">
            <div class="chip">Tabular View</div>
            <div class="muted small">Results are shown per student; expand to view semester & subject details.</div>
            <div class="ms-auto muted small" id="last_updated"></div>
          </div>
        </div>

        <div class="table-area" id="table_area">
          <table class="results-table" id="results_table" role="grid" aria-describedby="summary_count">
            <thead>
              <tr>
                <th style="width:48px"><input id="select_all" type="checkbox"></th>
                <th class="col-student">Student</th>
                <th class="col-sem">Sem</th>
                <th class="col-cgpa">CGPA / SGPA</th>
                <th class="col-backlog">Backlog</th>
                <th>Subjects</th>
                <th style="width:64px" class="sticky-right">Action</th>
              </tr>
            </thead>
            <tbody id="results_body">
              <tr><td colspan="7" class="text-muted p-4">No data loaded. Choose filters and click <strong>View Results</strong>.</td></tr>
            </tbody>
          </table>
        </div> <!-- /.table-area -->
      </div> <!-- /.results-card -->
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  (function(){
    const modeRadios = document.getElementsByName('mode');
    const classBlock = document.getElementById('class_block');
    const batchSelect = document.getElementById('batch_select');
    const classSelect = document.getElementById('class_select');
    const semSelect = document.getElementById('sem_select');
    const viewBtn = document.getElementById('view_btn');
    const resultsBody = document.getElementById('results_body');
    const summaryCount = document.getElementById('summary_count');
    const summaryInfo = document.getElementById('summary_info');
    const searchInput = document.getElementById('search_input');
    const selectAll = document.getElementById('select_all');
    const exportCsv = document.getElementById('export_csv');
    const lastUpdated = document.getElementById('last_updated');

    function currentMode() {
      for (const r of modeRadios) if (r.checked) return r.value;
      return 'batch';
    }

    for (const r of modeRadios) {
      r.addEventListener('change', () => {
        classBlock.style.display = (currentMode() === 'class') ? 'block' : 'none';
      });
    }

    // load classes for selected batch via AJAX
    async function loadClasses(batchId) {
      classSelect.innerHTML = '<option value="0">-- Loading --</option>';
      try {
        const resp = await fetch('?action=get_classes&batch_id=' + encodeURIComponent(batchId));
        const j = await resp.json();
        if (j.status) {
          const cls = j.classes || [];
          let html = '<option value="0">-- Select Class --</option>';
          for (const c of cls) html += `<option value="${c.id}">${escapeHtml(c.classname)}</option>`;
          classSelect.innerHTML = html;
        } else {
          classSelect.innerHTML = '<option value="0">-- No classes --</option>';
        }
      } catch (err) {
        classSelect.innerHTML = '<option value="0">-- Error --</option>';
      }
    }

    batchSelect.addEventListener('change', (e) => {
      const bid = parseInt(e.target.value) || 0;
      if (bid > 0) loadClasses(bid);
    });

    // escape helper
    function escapeHtml(s){ if (!s) return ''; return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    // Render results - server returns { status:true, data:{students: [ ... ] } }
    function renderResults(j) {
      const students = (j && j.data && j.data.students) ? j.data.students : [];
      if (!students.length) {
        resultsBody.innerHTML = '<tr><td colspan="7" class="text-muted p-4">No results found for the selected filters.</td></tr>';
        summaryCount.textContent = '0 results';
        summaryInfo.textContent = 'No students';
        lastUpdated.textContent = new Date().toLocaleString();
        return;
      }
      let html = '';
      for (const st of students) {
        const firstSem = (st.semesters && st.semesters.length) ? st.semesters[0] : null;
        const studentLabel = `<div><strong>${escapeHtml(st.student_full_name||'—')}</strong></div>
          <div class="muted small">Enroll: ${escapeHtml(st.enrollment_no||'')} • GR: ${escapeHtml(st.gr_no||'')}</div>
          <div class="muted small">Class: ${escapeHtml(st.class_name||'-')} • Batch: ${escapeHtml(st.batch_start_year||'')}</div>`;
        html += `<tr class="student-row" data-stid="${st.student_id}">
          <td><input type="checkbox" class="select-st" data-stid="${st.student_id}"></td>
          <td class="col-student">${studentLabel}</td>
          <td class="col-sem">${firstSem ? 'Sem ' + (firstSem.sem_number || '-') : '-'}</td>
          <td class="col-cgpa">${st.cgpa !== null ? st.cgpa : (firstSem && firstSem.sgpa !== null ? firstSem.sgpa : '-')}</td>
          <td class="col-backlog">${firstSem ? (firstSem.backlog !== null ? firstSem.backlog : '-') : '-'}</td>
          <td>${(st.semesters || []).length} semester(s)</td>
          <td class="sticky-right"><button class="btn btn-sm btn-light expand-btn" data-stid="${st.student_id}">View</button></td>
        </tr>`;

        // detail row hidden
        let details = '<div class="p-2">';
        for (const sem of (st.semesters || [])) {
          details += `<div style="margin-bottom:10px;border:1px solid #eef3fb;border-radius:8px;padding:8px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <div style="font-weight:700">Semester ${escapeHtml(String(sem.sem_number||''))} • ${escapeHtml(sem.edu_type||'')}</div>
              <div class="muted small">${escapeHtml(sem.result||'')} • SGPA: ${sem.sgpa !== null ? sem.sgpa : '-'} • Backlog: ${sem.backlog !== null ? sem.backlog : 0}</div>
            </div>
            <div style="overflow:auto"><table style="width:100%;border-collapse:collapse">
              <thead><tr style="background:#fafbfd"><th style="padding:8px 10px;width:120px;text-align:left">Code</th><th style="padding:8px 10px;text-align:left">Subject</th><th style="padding:8px 10px;width:96px;text-align:right">Grade</th></tr></thead>
              <tbody>`;
          if (sem.subjects && sem.subjects.length) {
            for (const sub of sem.subjects) {
              details += `<tr><td style="padding:8px 10px;width:120px">${escapeHtml(sub.subject_code||'')}</td>
                <td style="padding:8px 10px">${escapeHtml(sub.subject_name||'')}</td>
                <td style="padding:8px 10px;width:96px;text-align:right">${escapeHtml(sub.grade||'-')}</td></tr>`;
            }
          } else {
            details += `<tr><td colspan="3" style="padding:12px;color:#666">No subjects uploaded for this semester.</td></tr>`;
          }
          details += `</tbody></table></div></div>`;
        }
        details += '</div>';
        html += `<tr class="detail-row" data-stid="${st.student_id}" style="display:none"><td colspan="7">${details}</td></tr>`;
      }

      resultsBody.innerHTML = html;
      summaryCount.textContent = `${students.length} student(s)`;
      summaryInfo.textContent = `Click 'View' to expand student semesters`;
      lastUpdated.textContent = new Date().toLocaleString();

      // wire up expand buttons
      document.querySelectorAll('.expand-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const stid = btn.getAttribute('data-stid');
          const detail = document.querySelector('.detail-row[data-stid="'+stid+'"]');
          if (!detail) return;
          if (detail.style.display === 'none' || detail.style.display === '') {
            detail.style.display = 'table-row';
            btn.textContent = 'Hide';
          } else {
            detail.style.display = 'none';
            btn.textContent = 'View';
          }
        });
      });

      // select toggle handlers
      document.querySelectorAll('.select-st').forEach(cb => {
        cb.addEventListener('change', () => {
          // update select_all status
          const total = document.querySelectorAll('.select-st').length;
          const checked = document.querySelectorAll('.select-st:checked').length;
          selectAll.checked = (total === checked && total > 0);
        });
      });
    }

    async function fetchResults() {
      const sem = parseInt(semSelect.value || 0);
      const batch = parseInt(batchSelect.value || 0);
      const cls = parseInt(classSelect.value || 0);
      const mode = currentMode();
      if (batch <= 0) { alert('Please select a batch (branch).'); return; }
      if (mode === 'class' && cls <= 0) { alert('Please select a class.'); return; }

      viewBtn.disabled = true; viewBtn.textContent = 'Loading...';
      try {
        const body = new URLSearchParams();
        body.append('action','fetch_results');
        body.append('sem_info_id', String(sem));
        body.append('batch_info_id', String(batch));
        body.append('class_info_id', String(mode === 'class' ? cls : 0));

        const resp = await fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() });
        const j = await resp.json();
        if (!j.status) { alert(j.message || 'No data'); resultsBody.innerHTML = '<tr><td colspan="7" class="text-muted p-4">Server returned no data</td></tr>'; }
        else renderResults(j);
      } catch (err) {
        console.error(err);
        resultsBody.innerHTML = '<tr><td colspan="7" class="text-muted p-4">Failed to load results</td></tr>';
      } finally {
        viewBtn.disabled = false; viewBtn.textContent = 'View Results';
      }
    }

    viewBtn.addEventListener('click', fetchResults);

    // select all logic
    selectAll.addEventListener('change', () => {
      const checked = selectAll.checked;
      document.querySelectorAll('.select-st').forEach(cb => cb.checked = checked);
    });

    // column toggles
    document.querySelectorAll('.col-toggle').forEach(el => el.addEventListener('change', (ev) => {
      const map = { 'col_student': '.col-student', 'col_sem': '.col-sem', 'col_cgpa': '.col-cgpa', 'col_backlog': '.col-backlog' };
      const id = ev.target.id;
      const sel = map[id];
      document.querySelectorAll(sel).forEach(node => node.style.display = ev.target.checked ? '' : 'none');
      document.querySelectorAll('thead th.' + (sel.replace('.','.'))).forEach(h => h.style.display = ev.target.checked ? '' : 'none');
    }));

    // export selected to CSV (client-side)
    exportCsv.addEventListener('click', () => {
      const rows = [];
      document.querySelectorAll('.select-st:checked').forEach(cb => {
        const stid = cb.getAttribute('data-stid');
        const studentRow = document.querySelector('.student-row[data-stid="'+stid+'"]');
        const detailRow = document.querySelector('.detail-row[data-stid="'+stid+'"]');
        if (!studentRow) return;
        const studentName = studentRow.querySelector('td.col-student').innerText.replace(/\n/g,' ').trim();
        // gather semesters and subjects text
        let semsText = '';
        if (detailRow) {
          // crude extraction: list semesters and subjects
          detailRow.querySelectorAll('div[style*="Semester"]').forEach(d => {});
          semsText = detailRow.innerText.replace(/\r?\n+/g,' | ').trim();
        }
        rows.push([stid, studentName, semsText]);
      });
      if (!rows.length) { alert('Select at least one student'); return; }
      let csv = 'student_id,student,details\n';
      for (const r of rows) csv += `"${r[0]}","${r[1].replace(/"/g,'""')}","${r[2].replace(/"/g,'""')}"\n`;
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = 'results_export.csv'; a.click();
      URL.revokeObjectURL(url);
    });

    // client-side search (on visible student rows)
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('#results_body tr.student-row').forEach(r => {
        const text = r.textContent.toLowerCase();
        const show = text.indexOf(q) >= 0;
        r.style.display = show ? '' : 'none';
        const stid = r.getAttribute('data-stid');
        const detail = document.querySelector('.detail-row[data-stid="'+stid+'"]');
        if (detail) detail.style.display = show ? (detail.style.display === 'table-row' ? 'table-row' : 'none') : 'none';
      });
    });

    // init: load classes for initial batch selection if any
    (function init() {
      const bid = parseInt(batchSelect.value || 0);
      if (bid > 0) loadClasses(bid);
    })();

  })();
  </script>
</body>
</html>

<?php
// ----------------- PHP helper functions -----------------

function fetch_all_sem_info() {
    global $conn;
    $out = [];
    $sql = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) $out[] = $r;
        $res->free();
    }
    return $out;
}

function fetch_all_batches() {
    global $conn;
    $out = [];
    $sql = "SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year DESC";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) $out[] = $r;
        $res->free();
    }
    return $out;
}

function fetch_classes_for_batch($batch_id) {
    global $conn;
    $out = [];
    if (!$batch_id) return $out;
    $stmt = $conn->prepare("SELECT id, classname FROM class_info WHERE batch_id = ? ORDER BY classname");
    if (!$stmt) return $out;
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

function fetch_results($sem_info_id, $batch_info_id, $class_info_id) {
    global $conn;
    if (!$batch_info_id) return ['status'=>false, 'message'=>'batch_info_id is required'];

    $sql = "
      SELECT
        si.id AS student_id, si.enrollment_no, si.gr_no, si.student_full_name, si.cgpa,
        ci.id AS class_info_id, ci.classname, bi.id AS batch_info_id, bi.batch_start_year, bi.batch_end_year,
        srs.sem_info_id, sem.sem AS sem_number, sem.edu_type, srs.backlog, srs.sgpa, srs.result,
        sub.id AS subject_id, sub.subject_code, sub.subject_name, ssg.grade
      FROM student_result_semester srs
      JOIN student_info si ON si.id = srs.student_id
      LEFT JOIN class_info ci ON ci.id = si.class_info_id
      LEFT JOIN batch_info bi ON bi.id = ci.batch_id
      LEFT JOIN sem_info sem ON sem.id = srs.sem_info_id
      LEFT JOIN student_subject_grade ssg ON ssg.student_id = si.id
      LEFT JOIN subject_info sub ON sub.id = ssg.subject_id AND sub.sem_info_id = srs.sem_info_id
      WHERE bi.id = ?
        AND ( ? = 0 OR ci.id = ? )
        AND ( ? = 0 OR srs.sem_info_id = ? )
      ORDER BY si.student_full_name, srs.sem_info_id, COALESCE(sub.subject_code,'')
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['status'=>false, 'message'=>'Prepare failed: ' . $conn->error];
    $stmt->bind_param("iiiii", $batch_info_id, $class_info_id, $class_info_id, $sem_info_id, $sem_info_id);
    if (!$stmt->execute()) {
        $msg = $stmt->error; $stmt->close();
        return ['status'=>false, 'message'=>'Execute failed: ' . $msg];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    // Group into students -> semesters -> subjects
    $students = [];
    foreach ($rows as $r) {
        $sid = intval($r['student_id']);
        if (!isset($students[$sid])) {
            $students[$sid] = [
                'student_id' => $sid,
                'enrollment_no' => $r['enrollment_no'],
                'gr_no' => $r['gr_no'],
                'student_full_name' => $r['student_full_name'],
                'cgpa' => $r['cgpa'] !== null ? floatval($r['cgpa']) : null,
                'class_info_id' => $r['class_info_id'] ? intval($r['class_info_id']) : null,
                'class_name' => $r['classname'] ?? null,
                'batch_info_id' => $r['batch_info_id'] ? intval($r['batch_info_id']) : null,
                'batch_start_year' => $r['batch_start_year'],
                'batch_end_year' => $r['batch_end_year'],
                'semesters' => []
            ];
        }
        $semKey = intval($r['sem_info_id']);
        if ($semKey === 0) continue;
        if (!isset($students[$sid]['semesters'][$semKey])) {
            $students[$sid]['semesters'][$semKey] = [
                'sem_info_id' => $semKey,
                'sem_number' => $r['sem_number'] !== null ? intval($r['sem_number']) : null,
                'edu_type' => $r['edu_type'] ?? null,
                'backlog' => $r['backlog'] !== null ? intval($r['backlog']) : null,
                'sgpa' => $r['sgpa'] !== null ? floatval($r['sgpa']) : null,
                'result' => $r['result'] ?? null,
                'subjects' => []
            ];
        }
        if ($r['subject_id']) {
            $students[$sid]['semesters'][$semKey]['subjects'][] = [
                'subject_id' => intval($r['subject_id']),
                'subject_code' => $r['subject_code'],
                'subject_name' => $r['subject_name'],
                'grade' => $r['grade']
            ];
        }
    }

    // Convert map to indexed arrays with sorted semesters
    $outStudents = [];
    foreach ($students as $st) {
        $semArr = [];
        if (!empty($st['semesters'])) {
            ksort($st['semesters'], SORT_NUMERIC);
            foreach ($st['semesters'] as $s) $semArr[] = $s;
        }
        $st['semesters'] = $semArr;
        $outStudents[] = $st;
    }

    return ['status'=>true, 'data'=>['students'=>$outStudents]];
}
?>