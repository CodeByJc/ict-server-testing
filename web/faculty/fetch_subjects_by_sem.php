<?php
// fetch_subjects_by_sem.php
// POST params:
//   - sem_id (required, int)
//   - batch_id (optional, int)
// Returns JSON array of subject rows, each including batch_id and batch_start_year/batch_end_year when available.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// adjust path if necessary
$includePath = '../../api/db/db_connection.php';
if (!file_exists($includePath)) {
    http_response_code(500);
    echo json_encode(['error' => "DB connection file not found: {$includePath}"]);
    exit;
}
require_once $includePath;
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Read input
$sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;
$batch_id = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? intval($_POST['batch_id']) : 0;

if ($sem_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'sem_id is required and must be a positive integer']);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Build base SQL. We will LEFT JOIN batch_info (or batch_info table name if different)
    // Assumptions: subjects table is `subject_info`, batches table is `batch_info`.
    // subject_info columns: id, subject_name, short_name, subject_code, sem_info_id, type, lec_type, is_creditable, batch_id
    // batch_info columns: id, batch_start_year, batch_end_year, edu_type

    $sql = "SELECT 
                s.id,
                s.subject_name,
                s.short_name,
                s.subject_code,
                s.sem_info_id,
                s.type,
                s.lec_type,
                s.is_creditable,
                s.batch_id,
                bi.batch_start_year,
                bi.batch_end_year,
                bi.edu_type
            FROM subject_info s
            LEFT JOIN batch_info bi ON s.batch_id = bi.id
            WHERE s.sem_info_id = ?";

    $params = [];
    $types = '';
    $params[] = $sem_id;
    $types .= 'i';

    if ($batch_id > 0) {
        $sql .= " AND s.batch_id = ?";
        $params[] = $batch_id;
        $types .= 'i';
    }

    $sql .= " ORDER BY s.subject_name ASC, s.subject_code ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        throw new Exception('DB prepare failed: ' . mysqli_error($conn));
    }

    // bind params dynamically
    if (!empty($params)) {
        // Build refs for bind_param
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
    $res = mysqli_stmt_get_result($stmt);

    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Normalize keys and types
        $out[] = [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'subject_name' => $row['subject_name'] ?? '',
            'short_name' => $row['short_name'] ?? '',
            'subject_code' => $row['subject_code'] ?? '',
            'sem_info_id' => isset($row['sem_info_id']) ? (int)$row['sem_info_id'] : null,
            'type' => $row['type'] ?? '',
            'lec_type' => $row['lec_type'] ?? '',
            'is_creditable' => isset($row['is_creditable']) ? (int)$row['is_creditable'] : null,
            'batch_id' => isset($row['batch_id']) ? (int)$row['batch_id'] : null,
            'batch_start_year' => isset($row['batch_start_year']) ? $row['batch_start_year'] : null,
            'batch_end_year' => isset($row['batch_end_year']) ? $row['batch_end_year'] : null,
            'batch_edu_type' => $row['edu_type'] ?? null
        ];
    }

    mysqli_stmt_close($stmt);
    echo json_encode($out);
    exit;
} catch (Exception $e) {
    if (isset($stmt) && is_object($stmt)) mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch subjects: ' . $e->getMessage()]);
    exit;
}
?>