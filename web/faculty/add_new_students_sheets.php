<?php
ob_start(); // Start output buffering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../api/db/db_connection.php';
require '../vendor/autoload.php'; // PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check DB connection (assuming db_connection.php provides $conn)
if (!isset($conn) || mysqli_connect_errno()) {
    error_log("Database connection failed or \$conn missing: " . (isset($conn) ? mysqli_connect_error() : 'no $conn'));
    die("Database connection failed");
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'] ?? null;
$user = $_SESSION['user'] ?? null;

if (!isset($_SESSION['image_url']) && isset($user['username'])) {
    $imageUrl = "https://marwadieducation.edu.in/MEFOnline/handler/getImage.ashx?Id=" . htmlspecialchars($user['username']);
    $_SESSION['image_url'] = $imageUrl;
} else {
    $imageUrl = $_SESSION['image_url'] ?? '';
}

// ---------- Helper: compute category for a given student row ----------
function compute_category_for_student($conn, $student) {
    // Step 1: Get batch_start_year from batch_info table
    $batch_start_year = null;
    if (!empty($student['batch_info_id']) && is_numeric($student['batch_info_id'])) {
        $candidate = (int)$student['batch_info_id'];
        $stmt = $conn->prepare("SELECT batch_start_year FROM batch_info WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $candidate);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $batch_start_year = (int)$row['batch_start_year'];
            }
            $stmt->close();
        }
    }

    // Step 2: fallback to current year if not found
    if (empty($batch_start_year) || !is_int($batch_start_year) || $batch_start_year <= 0) {
        $batch_start_year = (int)date("Y");
    }

    // Step 3: derive last two digits and next year code
    $batch_last_two = intval(substr((string)$batch_start_year, -2)); // e.g., 2023 → 23
    $batch_next_two = ($batch_last_two + 1) % 100;                   // e.g., 24

    // Step 4: extract enrollment code (2nd and 3rd digits)
    $enroll_code = null;
    $enrollment_no = $student['enrollment_no'] ?? '';
    if (strlen($enrollment_no) >= 3) {
        $enroll_code_str = substr($enrollment_no, 1, 2);
        if (is_numeric($enroll_code_str)) {
            $enroll_code = intval($enroll_code_str);
        }
    }

    // Step 5: decide category
    if ($enroll_code === $batch_last_two) {
        return 'REGULAR';
    } elseif ($enroll_code === $batch_next_two) {
        return 'D2D';
    } else {
        return 'UNKNOWN'; // optional fallback
    }
}


// Handle file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && !isset($_POST['action'])) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (in_array($fileExtension, ['xlsx', 'xls'])) {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $students = [];

            foreach ($sheetData as $rowIndex => $row) {
                // Skip header row(s); assuming first row is header
                if ($rowIndex == 1) {
                    continue;
                }

                // Skip rows without enrollment_no or gr_no
                $gr_no = isset($row['B']) ? trim($row['B']) : '';
                $enrollment_no = isset($row['C']) ? trim($row['C']) : '';
                if ($gr_no === '' && $enrollment_no === '') {
                    continue;
                }

                // compute stream and edu_type for preview & later insertion
                $stream_val = isset($row['D']) ? trim($row['D']) : '';
                $edu_type = 'DEGREE';
                if (stripos($stream_val, 'ICT') !== false || stripos($stream_val, 'ICT-DEGREE') !== false) {
                    $edu_type = 'DEGREE';
                }
                if (stripos($stream_val, 'DIPLOMA') !== false || stripos($stream_val, 'ICT-DIPLO') !== false) {
                    $edu_type = 'DIPLOMA';
                }

                $students[] = [
                    'gr_no' => $gr_no,
                    'enrollment_no' => $enrollment_no,
                    'stream' => $stream_val,
                    'sem' => isset($row['E']) ? trim($row['E']) : '',
                    'classname' => isset($row['F']) ? trim($row['F']) : null,
                    'batch_info_id' => isset($row['G']) ? trim($row['G']) : null,
                    'batch' => isset($row['H']) ? trim($row['H']) : '',
                    'student_full_name' => isset($row['I']) ? trim($row['I']) : '',
                    'gender' => isset($row['J']) ? strtoupper(trim($row['J'])) : '',
                    'phone_no' => isset($row['K']) ? trim($row['K']) : '',
                    'parent_phone_no' => isset($row['L']) ? trim($row['L']) : '',
                    'email_personal' => isset($row['M']) ? trim($row['M']) : '',
                    'mu_email' => isset($row['N']) ? trim($row['N']) : '',
                    'address_line' => isset($row['O']) ? trim($row['O']) : '',
                    'country_name' => isset($row['P']) ? trim($row['P']) : '',
                    'international_code' => isset($row['Q']) ? trim($row['Q']) : '',
                    'city' => isset($row['R']) ? trim($row['R']) : '',
                    'pincode' => isset($row['S']) ? trim($row['S']) : '',
                    'status' => isset($row['T']) ? trim($row['T']) : '',
                    'mentotr_id' => isset($row['U']) ? trim($row['U']) : '', // note: kept original key name per sheet 'mentotr_id'
                    'mentor_full_name' => isset($row['V']) ? trim($row['V']) : '',
                    'hostel_building' => isset($row['W']) ? trim($row['W']) : '',
                    'floor_no' => isset($row['X']) ? trim($row['X']) : '',
                    'room_no' => isset($row['Y']) ? trim($row['Y']) : '',
                    'edu_type' => $edu_type,
                    // category will be added below
                ];
            }

            // Compute and attach category for each student now (so preview displays it and insertion can use it)
            foreach ($students as $idx => $s) {
                $students[$idx]['category'] = compute_category_for_student($conn, $s);
            }

            // Store in session for preview
            $_SESSION['uploaded_students'] = $students;
            $_SESSION['upload_message'] = ['type' => 'success', 'text' => 'File uploaded successfully. Please review the data below.'];
        } catch (Exception $e) {
            error_log("Excel parsing error: " . $e->getMessage());
            $_SESSION['upload_message'] = ['type' => 'error', 'text' => 'Failed to parse Excel file.'];
        }
    } else {
        $_SESSION['upload_message'] = ['type' => 'error', 'text' => 'Only Excel files (.xlsx, .xls) are allowed.'];
    }

    header("Location: add_new_students_sheets.php");
    exit();
}

// Handle AJAX insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_students') {
    header('Content-Type: application/json');
    ob_end_clean();

    if (!isset($_SESSION['uploaded_students']) || empty($_SESSION['uploaded_students'])) {
        echo json_encode(['status' => 'error', 'message' => 'No data to insert']);
        exit;
    }

    $students = $_SESSION['uploaded_students'];
    $success_count = 0;
    $errors = [];

    // Start transaction
    $conn->begin_transaction();

    try {
        foreach ($students as $student) {
            // Validate required fields
            if (empty($student['enrollment_no']) || empty($student['gr_no']) || empty($student['student_full_name'])) {
                $errors[] = "Missing required fields for row with enrollment_no: {$student['enrollment_no']} / gr_no: {$student['gr_no']}";
                continue;
            }
            // Prepare some normalized values
            $enrollment_no = $student['enrollment_no'];
            $gr_no = $student['gr_no'];
            $student_full_name = trim($student['student_full_name'] ?? '');
            $nameParts = preg_split('/\s+/', $student_full_name, -1, PREG_SPLIT_NO_EMPTY);
            $first_name = '';
            $last_name = '';

            $count = count($nameParts);
            if ($count === 0) {
                $first_name = '';
                $last_name = '';
            } elseif ($count === 1) {
                $first_name = $nameParts[0];
                $last_name = '';
            } elseif ($count === 2) {
                $first_name = $nameParts[0];
                $last_name = $nameParts[1];
            } else { // 3 or more words
                $first_name = $nameParts[0];
                $last_name = $nameParts[2]; // pick the 3rd token as last name
            }

            // Gender normalization: DB expects 'M','F','O'
            $gender = strtoupper(substr(trim($student['gender']), 0, 1));
            if (!in_array($gender, ['M','F','O'])) {
                // Try mapping words
                $g = strtolower($student['gender']);
                if (strpos($g, 'male') !== false) $gender = 'M';
                elseif (strpos($g, 'female') !== false) $gender = 'F';
                else $gender = 'M'; // default fallback
            }

            // 1) Insert or ensure parent in user_login
            $parent_username = $gr_no;
            $parent_phone = $student['parent_phone_no'] ?: null;
            $parent_password = password_hash($first_name . '@' . $gr_no, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("SELECT username FROM user_login WHERE username = ?");
            $stmt->bind_param("s", $parent_username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows == 0) {
                $stmt_ins = $conn->prepare("INSERT INTO user_login (username, password, role, isactive, email, phone_no, device_token) VALUES (?, ?, 'parent', 1, NULL, ?, '')");
                $stmt_ins->bind_param("sss", $parent_username, $parent_password, $parent_phone);
                if (!$stmt_ins->execute()) {
                    throw new Exception("Failed to insert parent user_login for username {$parent_username}: " . $stmt_ins->error);
                }
                $stmt_ins->close();
            }
            $stmt->close();

            // 2) Insert into parents_info (parent_name required in schema)
            $parent_name = 'Parent of ' . $student_full_name;
            // But parents_info might already have an entry for this user_login (unique id not enforced), try to find an existing one
            $stmt = $conn->prepare("SELECT id FROM parents_info WHERE user_login_id = ?");
            $stmt->bind_param("s", $parent_username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $parent_info_id = $res->fetch_assoc()['id'];
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO parents_info (user_login_id, parent_name, address_info_id, profession) VALUES (?, ?, NULL, NULL)");
                $stmt_ins->bind_param("ss", $parent_username, $parent_name);
                if (!$stmt_ins->execute()) {
                    throw new Exception("Failed to insert parents_info for username {$parent_username}: " . $stmt_ins->error);
                }
                $parent_info_id = $stmt_ins->insert_id;
                $stmt_ins->close();
            }
            $stmt->close();

            // 3) Insert Student into user_login if not exists
            $student_username = $enrollment_no;
            $student_phone = $student['phone_no'] ?: null;
            $student_email = $student['mu_email'] ?: $student['email_personal'] ?: null;
            $student_password = password_hash($first_name . '@' . $enrollment_no, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("SELECT username FROM user_login WHERE username = ?");
            $stmt->bind_param("s", $student_username);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows == 0) {
                $stmt_ins = $conn->prepare("INSERT INTO user_login (username, password, role, isactive, email, phone_no, device_token) VALUES (?, ?, 'student', 1, ?, ?, '')");
                $stmt_ins->bind_param("ssss", $student_username, $student_password, $student_email, $student_phone);
                if (!$stmt_ins->execute()) {
                    throw new Exception("Failed to insert student user_login for username {$student_username}: " . $stmt_ins->error);
                }
                $stmt_ins->close();
            }
            $stmt->close();

            // 4) sem_info: determine edu_type based on stream
            $stream = strtoupper($student['stream']);
            $edu_type = 'degree';
            if (stripos($stream, 'DIPLOMA') !== false) {
                $edu_type = 'diploma';
            } elseif (stripos($stream, 'ICT-DIPLO') !== false) {
                $edu_type = 'diploma';
            }
            // sem value from column 
            $sem_val = $student['sem'] !== null && $student['sem'] !== '' ? (int)$student['sem'] : null;
            if ($sem_val === null || !is_int($sem_val) || $sem_val <= 0) {
                // fallback: try deriving sem from classname if possible (e.g., EK1 -> 1)
                if (preg_match('/\d+/', $student['classname'], $m)) {
                    $sem_val = (int)$m[0];
                } else {
                    $sem_val = 1; // default fallback
                }
            }

            // Ensure sem_info row exists
            $stmt = $conn->prepare("SELECT id FROM sem_info WHERE sem = ? AND edu_type = ?");
            $stmt->bind_param("is", $sem_val, $edu_type);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $sem_info_id = $res->fetch_assoc()['id'];
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO sem_info (sem, edu_type) VALUES (?, ?)");
                $stmt_ins->bind_param("is", $sem_val, $edu_type);
                if (!$stmt_ins->execute()) {
                    throw new Exception("Failed to insert sem_info for sem {$sem_val} / {$edu_type}: " . $stmt_ins->error);
                }
                $sem_info_id = $stmt_ins->insert_id;
                $stmt_ins->close();
            }
            $stmt->close();

            // 5) batch_info: use provided batch_info_id if numeric and exists; otherwise attempt to find by batch string or insert placeholder
            $batch_info_id = null;
            if (!empty($student['batch_info_id']) && is_numeric($student['batch_info_id'])) {
                $candidate = (int)$student['batch_info_id'];
                $stmt = $conn->prepare("SELECT id FROM batch_info WHERE id = ?");
                $stmt->bind_param("i", $candidate);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $batch_info_id = $candidate;
                }
                $stmt->close();
            }
            if ($batch_info_id === null) {
                // fallback: attempt to find a batch using batch string (G) - likely not possible; create a default batch (current year -> +4)
                $currentYear = (int)date("Y");
                $start = $currentYear;
                $end = $currentYear + 4;
                $stmt = $conn->prepare("SELECT id FROM batch_info WHERE batch_start_year = ? AND batch_end_year = ?");
                $stmt->bind_param("ii", $start, $end);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $batch_info_id = $res->fetch_assoc()['id'];
                } else {
                    $stmt_ins = $conn->prepare("INSERT INTO batch_info (batch_start_year, batch_end_year) VALUES (?, ?)");
                    $stmt_ins->bind_param("ii", $start, $end);
                    if (!$stmt_ins->execute()) {
                        throw new Exception("Failed to insert batch_info: " . $stmt_ins->error);
                    }
                    $batch_info_id = $stmt_ins->insert_id;
                    $stmt_ins->close();
                }
                $stmt->close();
            }

            // 6) mentor (faculty) lookup by faculty_info.faculty_id (mentotr_id column)
            $mentor_faculty_id = null;
            $mentor_db_id = null;
            if (!empty($student['mentotr_id'])) {
                $mfac = trim($student['mentotr_id']);
                $stmt = $conn->prepare("SELECT id FROM faculty_info WHERE user_login_id = ?");
                $stmt->bind_param("s", $mfac);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $mentor_db_id = (int)$res->fetch_assoc()['id'];
                    $mentor_faculty_id = $mfac;
                }
                $stmt->close();
            }

            // 7) class_info: find existing class or create
            $classname = $student['classname'] ?: 'UNKN';
            $batchLetter = strtolower(substr(trim($student['batch']), 0, 1)) ?: null; // class_info.batch enum expects 'a','b','c'
            if (!in_array($batchLetter, ['a','b','c'])) $batchLetter = null;

            // Try to find class_info matching classname + sem_info_id + batch
            if ($batchLetter) {
                $stmt = $conn->prepare("SELECT id FROM class_info WHERE classname = ? AND sem_info_id = ? AND batch = ?");
                $stmt->bind_param("sis", $classname, $sem_info_id, $batchLetter);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $class_info_id = (int)$res->fetch_assoc()['id'];
                } else {
                    // Insert with faculty_info_id if we have mentor_db_id
                    if ($mentor_db_id === null) {
                        $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch) VALUES (?, ?, ?)");
                        $stmt_ins->bind_param("sis", $classname, $sem_info_id, $batchLetter);
                    } else {
                        $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch, faculty_info_id) VALUES (?, ?, ?, ?)");
                        $stmt_ins->bind_param("sisi", $classname, $sem_info_id, $batchLetter, $mentor_db_id);
                    }
                    if (!$stmt_ins->execute()) {
                        throw new Exception("Failed to insert class_info for classname {$classname}: " . $stmt_ins->error);
                    }
                    $class_info_id = $stmt_ins->insert_id;
                    $stmt_ins->close();
                }
                $stmt->close();
            } else {
                // If no valid batch letter, try to find any class_info by classname & sem_info_id
                $stmt = $conn->prepare("SELECT id FROM class_info WHERE classname = ? AND sem_info_id = ?");
                $stmt->bind_param("si", $classname, $sem_info_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $class_info_id = (int)$res->fetch_assoc()['id'];
                } else {
                    // Insert simple class_info
                    if ($mentor_db_id === null) {
                        $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id) VALUES (?, ?)");
                        $stmt_ins->bind_param("si", $classname, $sem_info_id);
                    } else {
                        $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, faculty_info_id) VALUES (?, ?, ?)");
                        $stmt_ins->bind_param("sii", $classname, $sem_info_id, $mentor_db_id);
                    }
                    if (!$stmt_ins->execute()) {
                        throw new Exception("Failed to insert class_info for classname {$classname}: " . $stmt_ins->error);
                    }
                    $class_info_id = $stmt_ins->insert_id;
                    $stmt_ins->close();
                }
                $stmt->close();
            }


            // $classname = $student['classname'] ?: 'UNKN';

            // // prefer using numeric batch_info_id (FK) if available
            // if (!empty($batch_info_id) && is_int($batch_info_id)) {
            //     // find existing by classname + sem_info_id + batch_id
            //     $stmt = $conn->prepare("SELECT id FROM class_info WHERE classname = ? AND sem_info_id = ? AND batch_id = ?");
            //     $stmt->bind_param("sii", $classname, $sem_info_id, $batch_info_id);
            //     $stmt->execute();
            //     $res = $stmt->get_result();
            //     if ($res && $res->num_rows > 0) {
            //         $class_info_id = (int)$res->fetch_assoc()['id'];
            //         $stmt->close();
            //     } else {
            //         $stmt->close();
            //         // Insert using batch_id to satisfy FK
            //         if ($mentor_db_id === null) {
            //             $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch_id) VALUES (?, ?, ?)");
            //             $stmt_ins->bind_param("sii", $classname, $sem_info_id, $batch_info_id);
            //         } else {
            //             $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch_id, faculty_info_id) VALUES (?, ?, ?, ?)");
            //             $stmt_ins->bind_param("siii", $classname, $sem_info_id, $batch_info_id, $mentor_db_id);
            //         }
            //         if (!$stmt_ins->execute()) {
            //             throw new Exception("Failed to insert class_info for classname {$classname}: " . $stmt_ins->error);
            //         }
            //         $class_info_id = $stmt_ins->insert_id;
            //         $stmt_ins->close();
            //     }
            // } else {
            //     // fallback to legacy 'batch' letter column if provided (A/B/C)
            //     $batchLetter = strtoupper(substr(trim($student['batch']), 0, 1)) ?: null;
            //     if (!in_array($batchLetter, ['A','B','C'])) $batchLetter = null;

            //     if ($batchLetter) {
            //         $stmt = $conn->prepare("SELECT id FROM class_info WHERE classname = ? AND sem_info_id = ? AND batch = ?");
            //         $stmt->bind_param("sis", $classname, $sem_info_id, $batchLetter);
            //         $stmt->execute();
            //         $res = $stmt->get_result();
            //         if ($res && $res->num_rows > 0) {
            //             $class_info_id = (int)$res->fetch_assoc()['id'];
            //             $stmt->close();
            //         } else {
            //             $stmt->close();
            //             if ($mentor_db_id === null) {
            //                 $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch) VALUES (?, ?, ?)");
            //                 $stmt_ins->bind_param("sis", $classname, $sem_info_id, $batchLetter);
            //             } else {
            //                 $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, batch, faculty_info_id) VALUES (?, ?, ?, ?)");
            //                 $stmt_ins->bind_param("sisi", $classname, $sem_info_id, $batchLetter, $mentor_db_id);
            //             }
            //             if (!$stmt_ins->execute()) {
            //                 throw new Exception("Failed to insert class_info for classname {$classname}: " . $stmt_ins->error);
            //             }
            //             $class_info_id = $stmt_ins->insert_id;
            //             $stmt_ins->close();
            //         }
            //     } else {
            //         // last fallback: match only classname + sem_info_id or create without batch
            //         $stmt = $conn->prepare("SELECT id FROM class_info WHERE classname = ? AND sem_info_id = ?");
            //         $stmt->bind_param("si", $classname, $sem_info_id);
            //         $stmt->execute();
            //         $res = $stmt->get_result();
            //         if ($res && $res->num_rows > 0) {
            //             $class_info_id = (int)$res->fetch_assoc()['id'];
            //             $stmt->close();
            //         } else {
            //             $stmt->close();
            //             if ($mentor_db_id === null) {
            //                 $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id) VALUES (?, ?)");
            //                 $stmt_ins->bind_param("si", $classname, $sem_info_id);
            //             } else {
            //                 $stmt_ins = $conn->prepare("INSERT INTO class_info (classname, sem_info_id, faculty_info_id) VALUES (?, ?, ?)");
            //                 $stmt_ins->bind_param("sii", $classname, $sem_info_id, $mentor_db_id);
            //             }
            //             if (!$stmt_ins->execute()) {
            //                 throw new Exception("Failed to insert class_info for classname {$classname}: " . $stmt_ins->error);
            //             }
            //             $class_info_id = $stmt_ins->insert_id;
            //             $stmt_ins->close();
            //         }
            //     }
            // }

            // Determine category already computed at preview; ensure variable exists
            $category = $student['category'] ?? compute_category_for_student($conn, $student);

            // 9) Insert into addresses table for this student username (addresses.username references user_login.username)
            $address_line = $student['address_line'] ?: '';
            if (!empty($student['international_code'])) {
                $address_line = trim($address_line . ' ' . $student['international_code']);
            }
            $country_name = $student['country_name'] ?: null;
            $city = $student['city'] ?: null;
            $pincode = $student['pincode'] ?: null;

            // Check existing address for username
            $stmt = $conn->prepare("SELECT id FROM addresses WHERE username = ?");
            $stmt->bind_param("s", $student_username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                // Update existing address row
                $addr_id = $res->fetch_assoc()['id'];
                $stmt_up = $conn->prepare("UPDATE addresses SET address_line = ?, country_name = ?, city = ?, pincode = ? WHERE id = ?");
                $stmt_up->bind_param("ssssi", $address_line, $country_name, $city, $pincode, $addr_id);
                if (!$stmt_up->execute()) {
                    throw new Exception("Failed to update address for username {$student_username}: " . $stmt_up->error);
                }
                $stmt_up->close();
                $address_info_id = $addr_id;
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO addresses (username, address_line, country_name, city, pincode) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("sssss", $student_username, $address_line, $country_name, $city, $pincode);
                if (!$stmt_ins->execute()) {
                    throw new Exception("Failed to insert address_info: " . $stmt_ins->error);
                }
                $address_info_id = (int)$stmt_ins->insert_id;
                $stmt_ins->close();
            }
            $stmt->close();

            // 8) Insert into student_info
            $status_val = $student['status'] ?? null;
            $stream_val = $student['stream'] ?? null;

            // 1) check if student already exists
            $stmt = $conn->prepare("SELECT id FROM student_info WHERE enrollment_no = ? OR gr_no = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed (check student_info): " . $conn->error);
            }
            $stmt->bind_param("ss", $enrollment_no, $gr_no);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                // Already exists - fetch student id and skip insert
                $existing = $res->fetch_assoc();
                $student_info_id = (int)$existing['id'];
                $stmt->close();
            } else {
                $stmt->close();

                // Ensure numeric/NULL params are properly typed
                $class_info_id_param   = $class_info_id !== null ? (int)$class_info_id : null;
                $address_info_id_param = isset($address_info_id) && $address_info_id !== '' ? (int)$address_info_id : null;
                $parent_info_id_param  = isset($parent_info_id) && $parent_info_id !== '' ? (int)$parent_info_id : null;
                $mentor_id_param       = isset($mentor_db_id) && $mentor_db_id !== '' ? (int)$mentor_db_id : null;
                $sem_info_id_param     = isset($sem_info_id) ? (int)$sem_info_id : null;
                $batch_info_id_param   = isset($batch_info_id) ? (int)$batch_info_id : null;

                // Prepare INSERT - columns and placeholders must match exactly the bind order below
                $stmt_ins = $conn->prepare("
                    INSERT INTO student_info (
                        user_login_id,
                        enrollment_no,
                        gr_no,
                        class_info_id,
                        first_name,
                        last_name,
                        address_info_id,
                        gender,
                        parent_info_id,
                        mentor_id,
                        sem_info_id,
                        batch_info_id,
                        student_full_name,
                        email_personal,
                        status,
                        stream,
                        category
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt_ins) {
                    throw new Exception("Prepare failed for student_info INSERT: " . $conn->error);
                }

                // Bind types in the exact same order as placeholders:
                $bind_types = "sssissisiiiisssss"; // final 's' for category
                $stmt_ins->bind_param(
                    $bind_types,
                    $student_username,        // s
                    $enrollment_no,           // s
                    $gr_no,                   // s
                    $class_info_id_param,     // i
                    $first_name,              // s
                    $last_name,               // s
                    $address_info_id_param,   // i (NULL allowed)
                    $gender,                  // s
                    $parent_info_id_param,    // i (NULL allowed)
                    $mentor_id_param,         // i (NULL allowed)
                    $sem_info_id_param,       // i
                    $batch_info_id_param,     // i
                    $student_full_name,       // s
                    $student_email,           // s
                    $status_val,              // s
                    $stream_val,              // s
                    $category                 // s
                );

                if (!$stmt_ins->execute()) {
                    $err = $stmt_ins->error;
                    $stmt_ins->close();
                    throw new Exception("Failed to insert student_info for enrollment_no {$enrollment_no}: " . $err);
                }

                $student_info_id = (int)$stmt_ins->insert_id;
                $stmt_ins->close();
            }

            // 10) Hostels: if hostel_building present, insert or update hostels table for this student_info id
            if (!empty($student['hostel_building'])) {
                $building = $student['hostel_building'];
                $floor_raw = isset($student['floor_no']) ? trim((string)$student['floor_no']) : null;

                if ($floor_raw === null || $floor_raw === '') {
                    // no floor provided -> store SQL NULL
                    $floor_no = null;
                } else {
                    // keep whatever was provided, including "0"
                    $floor_no = $floor_raw;
                }
                $room_no = $student['room_no'] ?: null;

                // hostels has unique KEY student_id; either insert or update
                $stmt = $conn->prepare("SELECT id FROM hostels WHERE student_id = ?");
                $stmt->bind_param("i", $student_info_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    $hostel_id = $res->fetch_assoc()['id'];
                    $stmt_up = $conn->prepare("UPDATE hostels SET building = ?, floor_no = ?, room_no = ? WHERE id = ?");
                    $stmt_up->bind_param("sssi", $building, $floor_no, $room_no, $hostel_id);
                    if (!$stmt_up->execute()) {
                        throw new Exception("Failed to update hostels for student_id {$student_info_id}: " . $stmt_up->error);
                    }
                    $stmt_up->close();
                } else {
                    $stmt_ins = $conn->prepare("INSERT INTO hostels (student_id, building, floor_no, room_no) VALUES (?, ?, ?, ?)");
                    $stmt_ins->bind_param("isss", $student_info_id, $building, $floor_no, $room_no);
                    if (!$stmt_ins->execute()) {
                        throw new Exception("Failed to insert hostels for student_id {$student_info_id}: " . $stmt_ins->error);
                    }
                    $stmt_ins->close();
                }
                $stmt->close();
            }

            $success_count++;
        }

        // Commit transaction on success
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Insert error: " . $e->getMessage());
        $errors[] = "Exception: " . $e->getMessage();
    }

    // Clear session data
    unset($_SESSION['uploaded_students']);
    if ($success_count === count($students) && empty($errors)) {
        echo json_encode(['status' => 'success', 'message' => "Successfully inserted $success_count students."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Inserted $success_count students. Errors: " . implode(' | ', $errors)]);
    }
    exit;
}

// Handle AJAX clear preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_preview') {
    header('Content-Type: application/json');
    ob_end_clean();
    unset($_SESSION['uploaded_students']);
    echo json_encode(['status' => 'success', 'message' => 'Preview data cleared.']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Students from Excel</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #students-table {
            border-collapse: collapse;
            width: 100%;
        }
        #students-table th,
        #students-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            min-width: 150px;
            padding: 8px;
        }
        #students-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #students-table tbody tr:hover {
            background-color: #e5e7eb;
            font-weight: bold;
            cursor: pointer;
        }
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = "Add New Students from Excel";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <!-- Upload Form -->
            <div class="bg-white p-6 rounded-lg drop-shadow-xl mb-6">
                <h1 class="text-3xl font-bold">Upload Student Data</h1>
                <div class="rounded-full w-full h-1 mt-2 bg-slate-100"></div>
                <form id="upload-form" method="post" enctype="multipart/form-data" class="mt-4">
                    <div class="flex items-center space-x-4">
                        <input type="file" name="file" id="file" accept=".xlsx, .xls" class="border rounded p-2" required>
                        <button type="submit" class="bg-cyan-600 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                            <i class="fa-solid fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
                <?php if (isset($_SESSION['upload_message'])): ?>
                    <div class="mt-4 text-<?php echo $_SESSION['upload_message']['type'] === 'success' ? 'green' : 'red'; ?>-600">
                        <?php echo htmlspecialchars($_SESSION['upload_message']['text']); ?>
                    </div>
                    <?php unset($_SESSION['upload_message']); ?>
                <?php endif; ?>
            </div>
            <!-- Preview Table -->
            <?php if (isset($_SESSION['uploaded_students']) && !empty($_SESSION['uploaded_students'])): ?>
                <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold">Preview Student Data</h2>
                        <div class="space-x-2">
                            <button id="add-all-btn" class="bg-green-500 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                                <i class="fa-solid fa-plus"></i> Add All Data
                            </button>
                            <button id="clear-all-btn" class="bg-red-500 text-white p-2 px-5 rounded-full hover:px-7 transition-all">
                                <i class="fa-solid fa-trash"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="students-table" class="min-w-full bg-white shadow-md rounded-md">
                            <thead>
                                <tr class="bg-gray-700 text-white">
                                    <th class="border px-4 py-2 rounded-tl-md">Enrollment No</th>
                                    <th class="border px-4 py-2">GR No</th>
                                    <th class="border px-4 py-2">Full Name</th>
                                    <th class="border px-4 py-2">Gender</th>
                                    <th class="border px-4 py-2">Phone</th>
                                    <th class="border px-4 py-2">Parent Phone</th>
                                    <th class="border px-4 py-2">Email</th>
                                    <th class="border px-4 py-2">Stream</th>
                                    <th class="border px-4 py-2">Class</th>
                                    <th class="border px-4 py-2">Sem</th>
                                    <th class="border px-4 py-2">Edu Type</th>
                                    <th class="border px-4 py-2">Batch Info ID</th>
                                    <th class="border px-4 py-2">Batch</th>
                                    <th class="border px-4 py-2">Address</th>
                                    <th class="border px-4 py-2">Country</th>
                                    <th class="border px-4 py-2">Intl Code</th>
                                    <th class="border px-4 py-2">City</th>
                                    <th class="border px-4 py-2">Pincode</th>
                                    <th class="border px-4 py-2">Status</th>
                                    <th class="border px-4 py-2">category</th>
                                    <th class="border px-4 py-2">Mentor Faculty ID</th>
                                    <th class="border px-4 py-2">Mentor Name</th>
                                    <th class="border px-4 py-2">Hostel Building</th>
                                    <th class="border px-4 py-2">Floor No</th>
                                    <th class="border px-4 py-2 rounded-tr-md">Room No</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['uploaded_students'] as $student): ?>
                                    <tr>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['enrollment_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['gr_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['student_full_name'] ?? ''); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['gender']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['phone_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['parent_phone_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['mu_email'] ?: $student['email_personal']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['stream']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['classname']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['sem']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($edu_type); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['batch_info_id']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['batch']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['address_line']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['country_name']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['international_code']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['city']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['pincode']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['status']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['category'] ?? ''); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['mentotr_id']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['mentor_full_name']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['hostel_building']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['floor_no']); ?></td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($student['room_no']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with horizontal scrolling if table exists
            if ($('#students-table').length) {
                $('#students-table').DataTable({
                    scrollX: true,
                    paging: false,
                    searching: false,
                    ordering: true,
                    info: false,
                    columnDefs: [
                        { width: '150px', targets: '_all' }
                    ]
                });
            }

            // Handle Add All Data button
            $('#add-all-btn').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to add all data to the database?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, add it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'add_new_students_sheets.php',
                            method: 'POST',
                            data: { action: 'insert_students' },
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Success!', data.message, 'success').then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', data.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Insert AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to insert data. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });

            // Handle Clear All button
            $('#clear-all-btn').click(function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This will clear all preview data.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#06b6d4',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, clear it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'add_new_students_sheets.php',
                            method: 'POST',
                            data: { action: 'clear_preview' },
                            dataType: 'json',
                            success: function(data) {
                                if (data.status === 'success') {
                                    Swal.fire('Success!', data.message, 'success').then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error!', data.message || 'Failed to clear preview.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Clear AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error!', 'Failed to clear preview. Check the console for details.', 'error');
                            }
                        });
                    }
                });
            });

            // Show upload message if exists
            <?php if (isset($_SESSION['upload_message'])): ?>
                Swal.fire({
                    icon: '<?php echo $_SESSION['upload_message']['type']; ?>',
                    title: '<?php echo $_SESSION['upload_message']['type'] === 'success' ? 'Success' : 'Error'; ?>',
                    text: '<?php echo $_SESSION['upload_message']['text']; ?>',
                    confirmButtonColor: '#06b6d4'
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>
<?php ob_end_flush(); // Flush output buffer ?>