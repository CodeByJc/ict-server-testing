<?php
// faculty_login_service.php
// Service that verifies faculty credentials, returns JWT and faculty data.
// Requires a global $conn (mysqli). Uses firebase/php-jwt.

require_once __DIR__ . '/../vendor/autoload.php'; // path to composer autoload
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../db/db_connection.php'; // ensure $conn is available (adjust path)

function FacultyLoginService($username, $password, $device_token) {
    global $conn;

    // sanitize incoming username
    $username = $conn->real_escape_string($username);

    // 1) Ensure username exists (optional, helpful for clearer messages)
    $chk = $conn->prepare("SELECT COUNT(*) FROM user_login WHERE username = ?");
    if (!$chk) {
        return ['status' => false, 'message' => 'DB prepare failed (username check)'];
    }
    $chk->bind_param('s', $username);
    $chk->execute();
    $chk->bind_result($countUsers);
    $chk->fetch();
    $chk->close();

    if (empty($countUsers) || $countUsers == 0) {
        return ['status' => false, 'message' => 'Username not found'];
    }

    // 2) Fetch hashed password for active faculty
    $stmt = $conn->prepare("SELECT password FROM user_login WHERE username = ? AND role = 'faculty' AND isactive = 1 LIMIT 1");
    if (!$stmt) {
        return ['status' => false, 'message' => 'DB prepare failed (password fetch)'];
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (!$hashedPassword) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    if (!password_verify($password, $hashedPassword)) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // 3) Call stored procedure to fetch faculty details
    $stmt = $conn->prepare("CALL LoginFaculty(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'DB prepare failed (stored procedure)'];
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();

    // get_result may not be available in all mysqli setups with stored procedures,
    // but if available we use it; otherwise fetch via bind_result as fallback.
    $result = null;
    try {
        $result = $stmt->get_result();
    } catch (Exception $e) {
        // ignore and attempt fetch via mysqli_stmt_bind_result if necessary
        $result = null;
    }

    $faculty_data = null;
    if ($result && $result->num_rows > 0) {
        $faculty_data = $result->fetch_assoc();
    } else {
        // Fallback: try to fetch manually if get_result is not supported
        // (attempt to fetch any output using metadata)
        $meta = $stmt->result_metadata();
        if ($meta) {
            $fields = [];
            $row = [];
            while ($f = mysqli_fetch_field($meta)) {
                $fields[] = &$row[$f->name];
            }
            mysqli_free_result($meta);
            call_user_func_array([$stmt, 'bind_result'], $fields);
            if (mysqli_stmt_fetch($stmt)) {
                $faculty_data = [];
                foreach ($row as $k => $v) $faculty_data[$k] = $v;
            }
        }
    }

    // close statement and advance results to avoid "Commands out of sync" issues
    $stmt->close();
    // Important for stored procedures that return multiple result sets
    while ($conn->more_results() && $conn->next_result()) {
        $extra = $conn->use_result();
        if ($extra instanceof mysqli_result) {
            mysqli_free_result($extra);
        }
    }

    if (!$faculty_data) {
        return ['status' => false, 'message' => 'Failed to retrieve faculty details'];
    }

    // 4) Create JWT token (7 days expiry)
    $issuedAt = time();
    $expirationTime = $issuedAt + (7 * 24 * 60 * 60);
    $payload = [
        'username' => $username,
        'role' => 'faculty',
        'iat' => $issuedAt,
        'exp' => $expirationTime,
    ];

    // NOTE: keep secret key in config/env in real app
    $secretKey = 'a3e1b9e673d1f4c0e6e8d2b1a96f0e5c24b7f122e38b04a94d3cfab1a8f29c9d';
    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    // 5) Update device token if provided
    if (!empty($device_token)) {
        $up = $conn->prepare("UPDATE user_login SET device_token = ? WHERE username = ?");
        if ($up) {
            $up->bind_param('ss', $device_token, $username);
            $up->execute();
            $up->close();
        }
    }

    // 6) Return standardized response similar to StudentLoginService
    return [
        'status' => true,
        'token' => $jwt,
        'data' => [
            'faculty_details' => $faculty_data,
        ],
    ];
}

function GetFacultyListByStudentService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetFacultyListByStudent(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
        $faculty_data = [];
        while ($row = $result->fetch_assoc()) {
            $faculty_data[] = $row;
        }
        $stmt->close();
        if (count($faculty_data) > 0) {
            return ['status' => true, 'data' => $faculty_data];
        }else{
            return ['status' => false, 'message' => 'Invalid Student Id'];
        }
    $stmt->close();
    http_response_code(401); // Unauthorized
    return ['status' => false, 'message' => 'Invalid Student Id'];
}

function GetMentorByStudentService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetStudentMentorDetailsById(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $mentor_data = $result->fetch_assoc();
        $stmt->close();
        return ['status' => true, 'data' => $mentor_data];
    } else {
        $stmt->close();
        return ['status' => false, 'message' => 'Mentor not found'];
    }
}

// Fetch a directory list of faculty members, optionally filtered by designation
function GetFacultyDirectoryService($designation = null) {
    global $conn;

    // Base query selecting relevant columns; build full name if not stored
    $query = "SELECT * FROM faculty_info";
    $params = [];
    $types = '';

    if ($designation !== null && $designation !== '') {
        $query .= " WHERE designation = ?";
        $params[] = $designation;
        $types .= 's';
    }

    // Order alphabetically for predictable directory display
    $query .= " ORDER BY first_name, last_name";

    if (count($params) > 0) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['status' => false, 'message' => 'Failed to prepare faculty directory query: ' . $conn->error];
        }
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['status' => false, 'message' => 'Failed to prepare faculty directory query: ' . $conn->error];
        }
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        return ['status' => false, 'message' => 'Failed to execute query: ' . $error];
    }
    
    $result = $stmt->get_result();

    $faculty_directory = [];
    while ($row = $result->fetch_assoc()) {
        // Fallback if faculty_full_name column is NULL
        if (!isset($row['faculty_full_name']) || $row['faculty_full_name'] === null) {
            $row['faculty_full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
        $faculty_directory[] = $row;
    }
    $stmt->close();

    // Enrich with domain_name by looking up research_domain if available
    $domainIds = [];
    foreach ($faculty_directory as $item) {
        if (isset($item['domain_id']) && $item['domain_id'] !== null && $item['domain_id'] !== '') {
            $domainIds[] = (int)$item['domain_id'];
        }
    }
    $domainIds = array_values(array_unique($domainIds));

    if (count($domainIds) > 0) {
        $domainMap = _GetDomainNamesMap($domainIds);
        foreach ($faculty_directory as &$item) {
            $did = isset($item['domain_id']) ? (int)$item['domain_id'] : 0;
            $item['domain_name'] = $domainMap[$did] ?? null;
        }
        unset($item);
    }

    if (count($faculty_directory) === 0) {
        return ['status' => true, 'data' => ['faculty_directory' => [], 'count' => 0]]; // Empty but successful
    }

    return ['status' => true, 'data' => ['faculty_directory' => $faculty_directory, 'count' => count($faculty_directory)]];
}


?>
