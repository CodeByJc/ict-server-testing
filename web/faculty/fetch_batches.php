<?php
// fetch_batches.php
// Returns a JSON array of batches. Accepts POST:
// - sem_id (optional) -> attempt to filter by a matching semester column in batch_info if available
// - edu_type (optional) -> fallback filter by edu_type
//
// On success: returns JSON array: [ { id, batch_start_year, batch_end_year, edu_type }, ... ]
// On error: returns JSON object { "error": "message" }
//
// This version is defensive: it checks information_schema to see if batch_info has a semester column
// (sem_info_id, sem_id, semester_id, etc.). If not present, sem_id is ignored and all batches are returned
// (or filtered by edu_type if provided).

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed, use POST']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// include DB connection (adjust path if necessary)
$includePath = '../../api/db/db_connection.php';
if (!file_exists($includePath)) {
    http_response_code(500);
    echo json_encode(['error' => "Database connection file not found: {$includePath}"]);
    exit;
}
include($includePath); // expects $conn (mysqli)
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Input
$sem_id = isset($_POST['sem_id']) && $_POST['sem_id'] !== '' ? intval($_POST['sem_id']) : 0;
$edu_type = isset($_POST['edu_type']) ? trim($_POST['edu_type']) : '';

try {
    // Determine DB name for information_schema query
    $dbName = null;
    $resDb = mysqli_query($conn, "SELECT DATABASE() as dbname");
    if ($resDb) {
        $rowDb = mysqli_fetch_assoc($resDb);
        $dbName = $rowDb['dbname'] ?? null;
        mysqli_free_result($resDb);
    }

    // Candidate semester column names to check in batch_info
    $candidateCols = ['sem_info_id','sem_id','semester_id','seminfo_id'];

    $sem_column = null;
    if ($dbName) {
        // Build prepared IN-list dynamically
        $placeholders = implode(',', array_fill(0, count($candidateCols), '?'));
        $sqlCheck = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'batch_info' AND COLUMN_NAME IN ($placeholders) LIMIT 1";
        $stmtCheck = mysqli_prepare($conn, $sqlCheck);
        if ($stmtCheck) {
            // bind types: first s for dbName then strings for candidate column names
            $types = str_repeat('s', count($candidateCols) + 1);
            $bind_params = array_merge([$dbName], $candidateCols);
            // create references
            $a_params = [];
            $a_params[] = $types;
            foreach ($bind_params as $k => $v) {
                $a_params[] = &$bind_params[$k];
            }
            call_user_func_array([$stmtCheck, 'bind_param'], $a_params);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);
            if ($resCheck && ($c = mysqli_fetch_assoc($resCheck))) {
                $sem_column = $c['COLUMN_NAME'];
            }
            mysqli_stmt_close($stmtCheck);
        }
    }

    // Build the query depending on which filters are available
    if ($sem_id > 0 && $sem_column !== null) {
        // filter by semester column found
        $sql = "SELECT id, batch_start_year, batch_end_year, edu_type FROM batch_info WHERE `$sem_column` = ? ORDER BY batch_start_year DESC, batch_end_year DESC, id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception(mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, 'i', $sem_id);
    } elseif ($edu_type !== '') {
        // filter by edu_type
        $sql = "SELECT id, batch_start_year, batch_end_year, edu_type FROM batch_info WHERE edu_type = ? ORDER BY batch_start_year DESC, batch_end_year DESC, id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception(mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, 's', $edu_type);
    } else {
        // no filter (or sem_id provided but no matching column) -> return all batches
        $sql = "SELECT id, batch_start_year, batch_end_year, edu_type FROM batch_info ORDER BY batch_start_year DESC, batch_end_year DESC, id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception(mysqli_error($conn));
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $batches = [];
    if ($res !== false) {
        while ($row = mysqli_fetch_assoc($res)) {
            $batches[] = [
                'id' => isset($row['id']) ? (int)$row['id'] : null,
                'batch_start_year' => isset($row['batch_start_year']) ? (int)$row['batch_start_year'] : null,
                'batch_end_year' => isset($row['batch_end_year']) ? (int)$row['batch_end_year'] : null,
                'edu_type' => isset($row['edu_type']) ? (string)$row['edu_type'] : null,
            ];
        }
        mysqli_stmt_close($stmt);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to read batches from DB');
    }

    echo json_encode($batches);
    exit;
} catch (Exception $e) {
    // Return structured error JSON (400/500 depending)
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch batches: ' . $e->getMessage()]);
    exit;
}
?>