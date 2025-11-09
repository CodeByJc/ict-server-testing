<?php
// upload_results.php
// Faculty result upload (semester-wise) using Excel (PhpSpreadsheet).
// Fixed: avoid "Unknown column 'sem_info_id' in 'where clause'"
// - Ensure student_result_semester exists before preparing statements
// - Use backticked column/table names in SQL to avoid ambiguous identifiers
// - Correct bind_param type strings for INSERT/UPDATE statements
// - Add extra error reporting for debugging

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require '../../api/db/db_connection.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($conn) || !$conn) {
    die('Database connection not available.');
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../login.php');
    exit();
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

// Ensure result table exists (creates if missing)
function ensure_result_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `student_result_semester` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `sem_info_id` INT NOT NULL,
        `batch_info_id` INT DEFAULT NULL,
        `backlog` INT DEFAULT NULL,
        `sgpa` DECIMAL(4,2) DEFAULT NULL,
        `result` VARCHAR(32) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_student_sem` (`student_id`, `sem_info_id`),
        INDEX (`student_id`),
        INDEX (`sem_info_id`),
        INDEX (`batch_info_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        throw new Exception("Failed to ensure student_result_semester table: " . $conn->error);
    }
}

// Normalize header cell
function norm($s) {
    $s = (string)$s;
    $s = trim($s, " \t\n\r\0\x0B\xEF\xBB\xBF"); // strip whitespace and BOM
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

// ---------- Upload & Preview parsing ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && empty($_POST['action'])) {
    $sem_selected = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
    if ($sem_selected <= 0) {
        $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'Please select semester before uploading.'];
        header('Location: upload_results.php'); exit;
    }

    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'File upload error.'];
        header('Location: upload_results.php'); exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx','xls','csv'])) {
        $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'Only .xlsx, .xls or .csv allowed.'];
        header('Location: upload_results.php'); exit;
    }

    try {
        $spreadsheet = IOFactory::load($f['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 1) {
            $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'File contains no data.'];
            header('Location: upload_results.php'); exit;
        }

        // Detect header row (first non-empty row within first 5 rows)
        $firstHeaderRowIndex = 1;
        $maxRow = count($rows);
        $foundHeader = false;
        for ($i = 1; $i <= min(5, $maxRow); $i++) {
            $rowVals = array_map('norm', $rows[$i]);
            $nonEmpty = 0;
            foreach ($rowVals as $v) if ($v !== '') $nonEmpty++;
            if ($nonEmpty >= 3) { $firstHeaderRowIndex = $i; $foundHeader = true; break; }
        }
        if (!$foundHeader) $firstHeaderRowIndex = 1;

        // Build header map
        $header = [];
        foreach ($rows[$firstHeaderRowIndex] as $col => $val) {
            $header[$col] = norm($val);
        }

        // Lowercase header to find known columns
        $lcmap = [];
        foreach ($header as $col => $val) $lcmap[$col] = strtolower($val);

        $findCol = function(array $poss) use ($lcmap) {
            foreach ($lcmap as $col => $name) {
                foreach ($poss as $p) if ($name === strtolower($p)) return $col;
            }
            return null;
        };

        $col_enroll = $findCol(['enrollment_no','enrollment no','enrolment_no','enrollmentno']);
        $col_gr     = $findCol(['gr_no','gr no','grno','gr']);
        $col_name   = $findCol(['student_full_name','student name','name','full_name']);
        $col_seminfo= $findCol(['sem_info_id','sem_info','sem','semester']);
        $col_batchinfo= $findCol(['batch_info_id']);

        // Fallback to positional A/B/C/D mapping (user-specified layout)
        if (!$col_enroll && !$col_gr) {
            $colsList = array_keys($header);
            if (count($colsList) >= 4) {
                $col_gr = $colsList[0];
                $col_name = $colsList[1];
                $col_enroll = $colsList[2];
                $col_seminfo = $colsList[3];
            }
        }

        if (!$col_enroll && !$col_gr) {
            $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'File must contain enrollment_no or gr_no column.'];
            header('Location: upload_results.php'); exit;
        }

        // detect backlog/sgpa/cgpa/result
        $col_backlog = $findCol(['backlog','back logs','back-log']);
        $col_sgpa = null; $col_cgpa = null; $col_result = null;
        foreach ($lcmap as $col => $name) {
            if (strpos($name,'sgpa') !== false) $col_sgpa = $col;
            if (strpos($name,'cgpa') !== false) $col_cgpa = $col;
            if (in_array($name, ['result','status','pass/fail'])) $col_result = $col;
        }

        // Fetch subject codes for semester from DB
        // $stmt = $conn->prepare("SELECT `id`, `subject_code` FROM `subject_info` WHERE `sem_info_id` = ?");
        // $stmt->bind_param("i", $sem_selected);
        // $stmt->execute();
        // $res = $stmt->get_result();
        // $subject_by_code = [];
        // while ($r = $res->fetch_assoc()) {
        //     $subject_by_code[strtoupper(trim($r['subject_code']))] = (int)$r['id'];
        // }
        // $stmt->close();

        $stmt = $conn->prepare("SELECT `id`, `subject_code`, `sem_info_id`, `batch_id`, `type` FROM `subject_info` WHERE `sem_info_id` = ? OR `batch_id` IN (98,99) OR `sem_info_id` IN (15,16)");
        $stmt->bind_param("i", $sem_selected);
        $stmt->execute();
        $res = $stmt->get_result();
        $subject_by_code = [];
        while ($r = $res->fetch_assoc()) {
            $code = strtoupper(trim($r['subject_code']));
            if (!isset($subject_by_code[$code])) $subject_by_code[$code] = [];
            $subject_by_code[$code][] = $r; // store rows to choose best match later
        }
        $stmt->close();

        // Determine subject columns by matching header to subject codes or by position between sem and backlog
        $cols = array_keys($header);
        $subject_columns = [];
        $posSem = $col_seminfo ? array_search($col_seminfo, $cols) : null;
        $posBack = $col_backlog ? array_search($col_backlog, $cols) : null;
        foreach ($cols as $i => $col) {
            if (in_array($col, [$col_enroll, $col_gr, $col_name, $col_seminfo, $col_backlog, $col_sgpa, $col_cgpa, $col_result])) continue;
            $h = strtoupper($header[$col]);
            if (isset($subject_by_code[$h])) {
                $subject_columns[$col] = $h;
                continue;
            }
            if ($posSem !== null && $posBack !== null && $i > $posSem && $i < $posBack) {
                if (preg_match('/[A-Za-z].*\d|\d.*[A-Za-z]/', $h)) $subject_columns[$col] = $h;
            } elseif ($posSem !== null && $posBack === null && $i > $posSem) {
                if (preg_match('/[A-Za-z].*\d|\d.*[A-Za-z]/', $h)) $subject_columns[$col] = $h;
            } else {
                if (preg_match('/[A-Za-z].*\d|\d.*[A-Za-z]/', $h)) $subject_columns[$col] = $h;
            }
        }

        // parse data rows
        $parsed = [];
        $maxRow = count($rows);
        for ($r = $firstHeaderRowIndex + 1; $r <= $maxRow; $r++) {
            $row = $rows[$r];
            $enrollment_no = $col_enroll ? trim((string)($row[$col_enroll] ?? '')) : '';
            $gr_no = $col_gr ? trim((string)($row[$col_gr] ?? '')) : '';
            $stu_name = $col_name ? trim((string)($row[$col_name] ?? '')) : '';
            $row_seminfo = $col_seminfo ? (int)trim((string)($row[$col_seminfo] ?? $sem_selected)) : $sem_selected;
            $row_batchinfo = $col_batchinfo ? (int)trim((string)($row[$col_batchinfo] ?? 0)) : null;
            if ($enrollment_no === '' && $gr_no === '') continue;

            $entry = [
                'row' => $r,
                'enrollment_no' => $enrollment_no,
                'gr_no' => $gr_no,
                'student_full_name' => $stu_name,
                'sem_info_id' => $row_seminfo,
                'batch_info_id' => $row_batchinfo,
                'grades' => [],
                'backlog' => $col_backlog ? trim((string)($row[$col_backlog] ?? '')) : null,
                'sgpa' => $col_sgpa ? trim((string)($row[$col_sgpa] ?? '')) : null,
                'cgpa' => $col_cgpa ? trim((string)($row[$col_cgpa] ?? '')) : null,
                'result' => $col_result ? trim((string)($row[$col_result] ?? '')) : null,
            ];

            foreach ($subject_columns as $col => $codeHeader) {
                $raw = trim((string)($row[$col] ?? ''));
                if ($raw === '') continue;
                $code = strtoupper($codeHeader);
                if (!isset($subject_by_code[$code])) {
                    $cand = preg_replace('/\s+/', '', $code);
                    if (isset($subject_by_code[$cand])) $code = $cand;
                }
                $entry['grades'][$code] = $raw;
            }
            $parsed[] = $entry;
        }

        $_SESSION['result_upload_preview'] = [
            'sem_info_id' => $sem_selected,
            'header' => $header,
            'subject_map' => $subject_by_code,
            'subject_columns' => $subject_columns,
            'rows' => $parsed,
            'debug' => [
                'firstHeaderRowIndex' => $firstHeaderRowIndex,
                'headerCount' => count($header),
                'parsedCount' => count($parsed)
            ]
        ];
        $_SESSION['result_upload_message'] = ['type'=>'success','text'=>'File parsed. Preview ready. Parsed rows: '.count($parsed)];
    } catch (Exception $e) {
        $_SESSION['result_upload_message'] = ['type'=>'error','text'=>'Failed to parse file: '.$e->getMessage()];
    }

    header('Location: upload_results.php'); exit;
}

// ---------- Insert previewed results into DB ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_results') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['result_upload_preview'])) {
        echo json_encode(['status'=>'error','message'=>'No preview data found. Upload first.']); exit;
    }
    $preview = $_SESSION['result_upload_preview'];
    $sem_selected = intval($preview['sem_info_id']);
    $subject_by_code = $preview['subject_map'];
    $rows = $preview['rows'];

    // ensure table exists BEFORE preparing statements (this prevents "Unknown column 'sem_info_id' in 'where clause'")
    try {
        ensure_result_table($conn);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>'Failed to ensure result table: '.$e->getMessage()]); exit;
    }

    $report = ['inserted_rows'=>0,'inserted_grades'=>0,'updated_result_rows'=>0,'skipped_rows'=>0,'errors'=>[]];

    $conn->begin_transaction();
    // try {
    //     // prepare statements with backticked column names
    //     $stmt_find_student = $conn->prepare("SELECT `id`, `category`, `course`, `batch_id` FROM `student_info` WHERE `enrollment_no` = ? OR `gr_no` = ? LIMIT 1");
    //     $stmt_insert_grade = $conn->prepare("INSERT INTO `student_subject_grade` (`student_id`, `subject_id`, `grade`) VALUES (?, ?, ?)");
    //     $stmt_check_result = $conn->prepare("SELECT `id` FROM `student_result_semester` WHERE `student_id` = ? AND `sem_info_id` = ? AND COALESCE(`batch_info_id`,0) = ? LIMIT 1");
    //     $stmt_insert_result = $conn->prepare("INSERT INTO `student_result_semester` (`student_id`, `sem_info_id`, `batch_info_id`, `backlog`, `sgpa`, `result`) VALUES (?, ?, ?, ?, ?, ?)");
    //     $stmt_update_result = $conn->prepare("UPDATE `student_result_semester` SET `backlog` = ?, `sgpa` = ?, `result` = ? WHERE `id` = ?");
    //     $stmt_update_cgpa = $conn->prepare("UPDATE `student_info` SET `cgpa` = ? WHERE `id` = ?");

    //     if (!$stmt_find_student || !$stmt_insert_grade || !$stmt_check_result || !$stmt_insert_result || !$stmt_update_result || !$stmt_update_cgpa) {
    //         throw new Exception('Prepare failed: '.$conn->error);
    //     }

    //     foreach ($rows as $entry) {
    //         $enroll = $entry['enrollment_no'];
    //         $gr = $entry['gr_no'];
    //         $grades = $entry['grades'];

    //         $stmt_find_student->bind_param("ss", $enroll, $gr);
    //         $stmt_find_student->execute();
    //         $res = $stmt_find_student->get_result();
    //         if (!$res || $res->num_rows === 0) {
    //             $report['errors'][] = "Row {$entry['row']}: student not found (enroll: {$enroll}, gr: {$gr})";
    //             $report['skipped_rows']++;
    //             continue;
    //         }
    //         $student_row = $res->fetch_assoc();
    //         $student_id = (int)$student_row['id'];
    //         $student_category = strtoupper((string)$student_row['category']);

    //         // Apply rule: if sem_info_id < 3 and student.category is D2D, skip per-subject inserts
    //         $allow_subject_insert = true;
    //         if ($entry['sem_info_id'] < 3 && $student_category === 'D2D') $allow_subject_insert = false;

    //         $grade_count = 0;
    //         if ($allow_subject_insert && !empty($grades)) {
    //             foreach ($grades as $subcode => $gradeRaw) {
    //                 if (!isset($subject_by_code[$subcode])) {
    //                     $report['errors'][] = "Row {$entry['row']}: subject code {$subcode} not found for sem {$sem_selected}, skipped grade.";
    //                     continue;
    //                 }
    //                 $subject_id = (int)$subject_by_code[$subcode];
    //                 $g = strtoupper(trim($gradeRaw));
    //                 if ($g === '' || in_array($g, ['-','NA','N/A'])) continue;
    //                 $stmt_insert_grade->bind_param("iis", $student_id, $subject_id, $g);
    //                 if (!$stmt_insert_grade->execute()) {
    //                     $report['errors'][] = "Row {$entry['row']}: grade insert failed for student {$student_id}, subject {$subject_id}: ".$stmt_insert_grade->error;
    //                     continue;
    //                 }
    //                 $grade_count++; $report['inserted_grades']++;
    //             }
    //         }

    //         // semester-level data
    //         $backlog_val = null;
    //         if ($entry['backlog'] !== null && $entry['backlog'] !== '') {
    //             $bv = preg_replace('/\D/', '', $entry['backlog']);
    //             $backlog_val = $bv === '' ? null : (int)$bv;
    //         }
    //         $sgpa_val = ($entry['sgpa'] !== null && $entry['sgpa'] !== '') ? (float)str_replace(',', '.', $entry['sgpa']) : null;
    //         $result_val = $entry['result'] !== null ? strtoupper(trim($entry['result'])) : null;

    //         // $stmt_check_result->bind_param("ii", $student_id, $entry['sem_info_id']);
    //         // $stmt_check_result->execute();
    //         // $res_r = $stmt_check_result->get_result();
    //         // if ($res_r && $res_r->num_rows > 0) {
    //         //     $rid = (int)$res_r->fetch_assoc()['id'];
    //         //     // types: backlog (i), sgpa (d), result (s), id (i) -> "idsi"
    //         //     $stmt_update_result->bind_param("idsi", $backlog_val, $sgpa_val, $result_val, $rid);
    //         //     if (!$stmt_update_result->execute()) {
    //         //         $report['errors'][] = "Row {$entry['row']}: failed to update student_result_semester for student {$student_id}: ".$stmt_update_result->error;
    //         //     } else $report['updated_result_rows']++;
    //         // } else {
    //         //     // types: student_id (i), sem_info_id (i), backlog (i), sgpa (d), result (s) -> "iiids"
    //         //     $stmt_insert_result->bind_param("iiids", $student_id, $entry['sem_info_id'], $backlog_val, $sgpa_val, $result_val);
    //         //     if (!$stmt_insert_result->execute()) {
    //         //         $report['errors'][] = "Row {$entry['row']}: failed to insert student_result_semester for student {$student_id}: ".$stmt_insert_result->error;
    //         //     } else $report['updated_result_rows']++;
    //         // }

    //         // normalize batch id for comparison: use 0 when parsed null so COALESCE(...) works in query

    //         $batch_val = isset($entry['batch_info_id']) && $entry['batch_info_id'] !== null ? (int)$entry['batch_info_id'] : 0;

    //         // check if a result row exists for (student_id, sem_info_id, batch_info_id)
    //         $stmt_check_result->bind_param("iii", $student_id, $entry['sem_info_id'], $batch_val);
    //         $stmt_check_result->execute();
    //         $res_r = $stmt_check_result->get_result();

    //         if ($res_r && $res_r->num_rows > 0) {
    //             $rid = (int)$res_r->fetch_assoc()['id'];
    //             // update existing semester row (backlog (i), sgpa (d), result (s), id (i))
    //             $stmt_update_result->bind_param("idsi", $backlog_val, $sgpa_val, $result_val, $rid);
    //             if (!$stmt_update_result->execute()) {
    //                 $report['errors'][] = "Row {$entry['row']}: failed to update student_result_semester for student {$student_id}: ".$stmt_update_result->error;
    //             } else $report['updated_result_rows']++;
    //         } else {
    //             // insert new semester row with batch_info_id
    //             // types: student_id(i), sem_info_id(i), batch_info_id(i), backlog(i), sgpa(d), result(s) -> "iiiids"
    //             $stmt_insert_result->bind_param("iiiids", $student_id, $entry['sem_info_id'], $batch_val, $backlog_val, $sgpa_val, $result_val);
    //             if (!$stmt_insert_result->execute()) {
    //                 $report['errors'][] = "Row {$entry['row']}: failed to insert student_result_semester for student {$student_id}: ".$stmt_insert_result->error;
    //             } else $report['updated_result_rows']++;
    //         }


    //         if ($entry['cgpa'] !== null && $entry['cgpa'] !== '') {
    //             $cg = (float)str_replace(',', '.', $entry['cgpa']);
    //             $stmt_update_cgpa->bind_param("di", $cg, $student_id);
    //             if (!$stmt_update_cgpa->execute()) {
    //                 $report['errors'][] = "Row {$entry['row']}: failed to update cgpa for student {$student_id}: ".$stmt_update_cgpa->error;
    //             }
    //         }

    //         if ($grade_count > 0 || $backlog_val !== null || $sgpa_val !== null || $entry['result'] !== null) $report['inserted_rows']++;
    //         else $report['skipped_rows']++;
    //     }

    //     $conn->commit();
    // }
    try {
        // prepare statements with backticked column names
        $stmt_find_student = $conn->prepare("SELECT `id`, `category`, `stream`, `batch_info_id` FROM `student_info` WHERE `enrollment_no` = ? OR `gr_no` = ? LIMIT 1");
        $stmt_insert_grade = $conn->prepare("INSERT INTO `student_subject_grade` (`student_id`, `subject_id`, `grade`) VALUES (?, ?, ?)");
        $stmt_check_result = $conn->prepare("SELECT `id` FROM `student_result_semester` WHERE `student_id` = ? AND `sem_info_id` = ? AND COALESCE(`batch_info_id`,0) = ? LIMIT 1");
        $stmt_insert_result = $conn->prepare("INSERT INTO `student_result_semester` (`student_id`, `sem_info_id`, `batch_info_id`, `backlog`, `sgpa`, `result`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_update_result = $conn->prepare("UPDATE `student_result_semester` SET `backlog` = ?, `sgpa` = ?, `result` = ? WHERE `id` = ?");
        $stmt_update_cgpa = $conn->prepare("UPDATE `student_info` SET `cgpa` = ? WHERE `id` = ?");

        if (!$stmt_find_student || !$stmt_insert_grade || !$stmt_check_result || !$stmt_insert_result || !$stmt_update_result || !$stmt_update_cgpa) {
            throw new Exception('Prepare failed: '.$conn->error);
        }

        foreach ($rows as $entry) {
            $enroll = $entry['enrollment_no'];
            $gr = $entry['gr_no'];
            $grades = $entry['grades'];

            $stmt_find_student->bind_param("ss", $enroll, $gr);
            $stmt_find_student->execute();
            $res = $stmt_find_student->get_result();
            if (!$res || $res->num_rows === 0) {
                $report['errors'][] = "Row {$entry['row']}: student not found (enroll: {$enroll}, gr: {$gr})";
                $report['skipped_rows']++;
                continue;
            }
            $student_row = $res->fetch_assoc();
            $student_id = (int)$student_row['id'];
            $student_category = strtoupper((string)$student_row['category']);
            $student_stream = strtolower(trim((string)($student_row['stream'] ?? '')));
            $student_batch = (int)($student_row['batch_info_id'] ?? 0);

            // Apply rule: if sem_info_id < 3 and student.category is D2D, skip per-subject inserts
            $allow_subject_insert = true;
            if ($entry['sem_info_id'] < 3 && $student_category === 'D2D') $allow_subject_insert = false;

            $grade_count = 0;
            if ($allow_subject_insert && !empty($grades)) {
                foreach ($grades as $subcode => $gradeRaw) {
                    $key = strtoupper(trim((string)$subcode));
                    if (!isset($subject_by_code[$key])) {
                        $report['errors'][] = "Row {$entry['row']}: subject code {$key} not found for sem {$sem_selected}, skipped grade.";
                        continue;
                    }

                    // subject_by_code[$key] may be array of rows — choose best match
                    $subject_rows = $subject_by_code[$key];
                    $subject_id = null;

                    // 1) prefer exact sem_info_id match
                    foreach ($subject_rows as $sr) {
                        if ((int)$sr['sem_info_id'] === (int)$entry['sem_info_id']) { $subject_id = (int)$sr['id']; break; }
                    }

                    // 2) try ICT/ICT-DIPLOMA open-elective mapping when no exact sem match
                    if ($subject_id === null) {
                        if ($student_stream === 'ICT') {
                            foreach ($subject_rows as $sr) {
                                if ((int)$sr['batch_info_id'] === 99 && (int)$sr['sem_info_id'] === 15) { $subject_id = (int)$sr['id']; break; }
                            }
                        } elseif ($student_stream === 'ICT-DIPLOMA') {
                            foreach ($subject_rows as $sr) {
                                if ((int)$sr['batch_info_id'] === 98 && (int)$sr['sem_info_id'] === 16) { $subject_id = (int)$sr['id']; break; }
                            }
                        }
                    }

                    // 3) fallback to first available row
                    if ($subject_id === null) $subject_id = (int)$subject_rows[0]['id'];

                    $g = strtoupper(trim((string)$gradeRaw));
                    if ($g === '' || in_array($g, ['-','NA','N/A'])) continue;

                    $stmt_insert_grade->bind_param("iis", $student_id, $subject_id, $g);
                    if (!$stmt_insert_grade->execute()) {
                        $report['errors'][] = "Row {$entry['row']}: grade insert failed for student {$student_id}, subject {$subject_id}: ".$stmt_insert_grade->error;
                        continue;
                    }
                    $grade_count++; $report['inserted_grades']++;
                }
            }

            // semester-level data
            $backlog_val = null;
            if ($entry['backlog'] !== null && $entry['backlog'] !== '') {
                $bv = preg_replace('/\D/', '', $entry['backlog']);
                $backlog_val = $bv === '' ? null : (int)$bv;
            }
            $sgpa_val = ($entry['sgpa'] !== null && $entry['sgpa'] !== '') ? (float)str_replace(',', '.', $entry['sgpa']) : null;
            $result_val = $entry['result'] !== null ? strtoupper(trim((string)$entry['result'])) : null;

            // normalize batch id for comparison: use 0 when parsed null so COALESCE(...) works in query
            $batch_val = isset($entry['batch_info_id']) && $entry['batch_info_id'] !== null ? (int)$entry['batch_info_id'] : 0;

            // check if a result row exists for (student_id, sem_info_id, batch_info_id)
            $stmt_check_result->bind_param("iii", $student_id, $entry['sem_info_id'], $batch_val);
            $stmt_check_result->execute();
            $res_r = $stmt_check_result->get_result();

            if ($res_r && $res_r->num_rows > 0) {
                $rid = (int)$res_r->fetch_assoc()['id'];
                // update existing semester row (backlog (i), sgpa (d), result (s), id (i))
                $stmt_update_result->bind_param("idsi", $backlog_val, $sgpa_val, $result_val, $rid);
                if (!$stmt_update_result->execute()) {
                    $report['errors'][] = "Row {$entry['row']}: failed to update student_result_semester for student {$student_id}: ".$stmt_update_result->error;
                } else $report['updated_result_rows']++;
            } else {
                // insert new semester row with batch_info_id
                // types: student_id(i), sem_info_id(i), batch_info_id(i), backlog(i), sgpa(d), result(s) -> "iiiids"
                $stmt_insert_result->bind_param("iiiids", $student_id, $entry['sem_info_id'], $batch_val, $backlog_val, $sgpa_val, $result_val);
                if (!$stmt_insert_result->execute()) {
                    $report['errors'][] = "Row {$entry['row']}: failed to insert student_result_semester for student {$student_id}: ".$stmt_insert_result->error;
                } else $report['updated_result_rows']++;
            }

            if ($entry['cgpa'] !== null && $entry['cgpa'] !== '') {
                $cg = (float)str_replace(',', '.', $entry['cgpa']);
                $stmt_update_cgpa->bind_param("di", $cg, $student_id);
                if (!$stmt_update_cgpa->execute()) {
                    $report['errors'][] = "Row {$entry['row']}: failed to update cgpa for student {$student_id}: ".$stmt_update_cgpa->error;
                }
            }

            if ($grade_count > 0 || $backlog_val !== null || $sgpa_val !== null || $entry['result'] !== null) $report['inserted_rows']++;
            else $report['skipped_rows']++;
        }

        $conn->commit();
    } 
    catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Transaction failed: '.$e->getMessage()]); exit;
    }

    unset($_SESSION['result_upload_preview']);
    echo json_encode(['status'=>'ok','report'=>$report]); exit;
}

// Clear preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_preview') {
    unset($_SESSION['result_upload_preview']);
    $_SESSION['result_upload_message'] = ['type'=>'success','text'=>'Preview cleared.'];
    header('Location: upload_results.php'); exit;
}

// Page display (unchanged)
$sem_options = [];
$qr = $conn->query("SELECT `id`, `sem`, `edu_type` FROM `sem_info` ORDER BY `edu_type`, `sem`");
if ($qr) while ($r = $qr->fetch_assoc()) $sem_options[] = $r;

$uploadMessage = $_SESSION['result_upload_message'] ?? null;
unset($_SESSION['result_upload_message']);
$uploadPreview = $_SESSION['result_upload_preview'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Faculty - Upload Results (Semester-wise)</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
.table-container { overflow-x:auto; }
#preview-table th, #preview-table td { border:1px solid #e5e7eb; padding:8px; text-align:center; }
#preview-table th { background:#374151; color:#fff; }
.hint { font-size:0.9rem; color:#6b7280; }
.debug { font-size:0.9rem; color:#6b7280; margin-top:8px; }
</style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 overflow-y-auto">
    <?php $page_title="Upload Results"; include('./navbar.php'); ?>
    <div class="container mx-auto p-6">
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h1 class="text-xl font-bold">Upload Semester Results (Excel)</h1>
            <p class="hint mt-1">Columns required (case-insensitive): gr_no, student_full_name, enrollment_no (or gr_no), sem_info_id, subject columns (subject_code), backlog, sgpa, cgpa, result.</p>
            <?php if ($uploadMessage): ?>
                <div class="mt-3 text-<?php echo $uploadMessage['type']==='success'?'green':'red'; ?>-600"><?php echo h($uploadMessage['text']); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mt-4 flex gap-3 items-center">
                <div>
                    <label class="block text-sm font-medium">Semester</label>
                    <select name="sem_info_id" required class="p-2 border rounded">
                        <option value="">Select Semester</option>
                        <?php foreach ($sem_options as $s): ?>
                            <option value="<?php echo (int)$s['id']?>">SEM <?php echo h($s['sem']); ?> - <?php echo strtoupper(h($s['edu_type'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Excel file</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="pt-6">
                    <button type="submit" class="bg-cyan-600 text-white px-4 py-2 rounded-full">Upload & Preview</button>
                </div>
                <?php if ($uploadPreview): ?>
                    <div class="pt-6">
                        <form method="post" id="clearForm"><input type="hidden" name="action" value="clear_preview"><button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-full">Clear Preview</button></form>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($uploadPreview): 
            $preview = $uploadPreview;
            $subject_columns = $preview['subject_columns'];
            $rows = $preview['rows'];
            $debug = $preview['debug'] ?? null;
        ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Preview (<?php echo count($rows); ?> rows)</h2>
                    <div class="space-x-2">
                        <button id="insertAll" class="bg-green-500 text-white px-4 py-2 rounded-full">Insert All</button>
                        <form method="post" id="clearForm2" style="display:inline"><input type="hidden" name="action" value="clear_preview"><button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-full">Clear</button></form>
                    </div>
                </div>

                <div class="table-container">
                    <table id="preview-table" class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Enrollment No</th>
                                <th>GR No</th>
                                <th>Student Name</th>
                                <th>Sem</th>
                                <?php foreach ($subject_columns as $col => $code): ?>
                                    <th><?php echo h($code); ?></th>
                                <?php endforeach; ?>
                                <th>Backlog</th>
                                <th>SGPA</th>
                                <th>CGPA</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php echo (int)$r['row']; ?></td>
                                    <td><?php echo h($r['enrollment_no']); ?></td>
                                    <td><?php echo h($r['gr_no']); ?></td>
                                    <td><?php echo h($r['student_full_name']); ?></td>
                                    <td><?php echo h($r['sem_info_id']); ?></td>
                                    <?php foreach ($subject_columns as $col => $code): ?>
                                        <td><?php echo h($r['grades'][$code] ?? ''); ?></td>
                                    <?php endforeach; ?>
                                    <td><?php echo h($r['backlog']); ?></td>
                                    <td><?php echo h($r['sgpa']); ?></td>
                                    <td><?php echo h($r['cgpa']); ?></td>
                                    <td><?php echo h($r['result']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($debug): ?>
                    <div class="debug">Debug: headerRowIndex=<?php echo h($debug['firstHeaderRowIndex']); ?>, headerColumns=<?php echo h($debug['headerCount']); ?>, parsedRows=<?php echo h($debug['parsedCount']); ?></div>
                <?php endif; ?>

                <div class="mt-4 text-sm text-gray-600">
                    Notes:
                    <ul class="list-disc ml-6">
                        <li>If sem_info_id &lt; 3 and the student's category in DB is D2D, subject-grade rows will NOT be inserted (kept null) — semester-level data (backlog/sgpa/result) will still be recorded.</li>
                        <li>Subject headers must match subject_info.subject_code for the selected semester to be inserted.</li>
                        <li>Student is located by enrollment_no or gr_no. Missing students are skipped and reported.</li>
                    </ul>
                </div>

                <div id="insertReport" class="mt-4"></div>
            </div>

            <script>
                $(function(){
                    $('#insertAll').on('click', function(){
                        Swal.fire({
                            title: 'Insert all results?',
                            text: 'This will insert grades and update student CGPA/status where provided. Proceed?',
                            icon: 'question',
                            showCancelButton: true
                        }).then(function(res){
                            if (!res.isConfirmed) return;
                            $.ajax({
                                url: 'upload_results.php',
                                method: 'POST',
                                data: { action: 'insert_results' },
                                dataType: 'json',
                                success: function(resp){
                                    if (!resp) return Swal.fire('Error','No response from server','error');
                                    if (resp.status === 'ok') {
                                        const r = resp.report;
                                        let html = `<div class="p-4 bg-green-50 rounded"><strong>Inserted rows:</strong> ${r.inserted_rows}<br><strong>Inserted grades:</strong> ${r.inserted_grades}<br><strong>Updated/Inserted semester rows:</strong> ${r.updated_result_rows}<br><strong>Skipped rows:</strong> ${r.skipped_rows}</div>`;
                                        if (r.errors && r.errors.length) {
                                            html += '<div class="mt-3 p-3 bg-red-50 rounded"><strong>Errors:</strong><ul>';
                                            r.errors.forEach(e => html += `<li>${e}</li>`);
                                            html += '</ul></div>';
                                        }
                                        $('#insertReport').html(html);
                                        setTimeout(()=> location.reload(), 2000);
                                    } else {
                                        Swal.fire('Error', resp.message || 'Failed', 'error');
                                    }
                                },
                                error: function(xhr){
                                    Swal.fire('Error','AJAX failed. See console.','error');
                                    console.error(xhr.responseText);
                                }
                            });
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php ob_end_flush(); ?>