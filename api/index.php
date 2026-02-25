<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
require __DIR__ . '/utils/ApiKeyValidator.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(); // Stop further processing
}

// Use __DIR__ to make all require paths relative to this file

require_once __DIR__ . '/routes/AppVersionRoutes.php';
require_once __DIR__ . '/routes/AnnouncementRoutes.php';
require_once __DIR__ . '/routes/ParentRoutes.php';
require_once __DIR__ . '/routes/FacultyRoutes.php';
require_once __DIR__ . '/routes/StudentRoutes.php';
require_once __DIR__ . '/routes/PlacementRoutes.php';
require_once __DIR__ . '/routes/AttendanceRoutes.php';
require_once __DIR__ . '/routes/PasswordRoutes.php';
require_once __DIR__ . '/routes/ZoomRoutes.php';
require_once __DIR__ . '/routes/HolidayRoutes.php';
require_once __DIR__ . '/routes/ExamRoutes.php';
require_once __DIR__ . '/routes/LeaveRoutes.php';
require_once __DIR__ . '/routes/EventRoutes.php';
require_once __DIR__ . '/routes/FeedbackRoutes.php';
require_once __DIR__ . '/routes/ResultRoutes.php';
require_once __DIR__ . '/routes/InterviewBankRoutes.php';



// Validate API Key
validateApiKey(); // Check the API key before processing the request

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));

// Find index.php position and get endpoint/subpath after it
$indexPos = array_search('index.php', $segments);
$endpoint = $segments[$indexPos + 1] ?? null; // e.g. Faculty
$subpath  = $segments[$indexPos + 2] ?? null; // e.g. login

switch ($endpoint) {
    case 'AppVersion':
        AppVersionRoutes($method, $subpath);
        break;
    case 'Parent':
        ParentRoutes($method, $subpath);
        break;
    case 'Student':
        StudentRoutes($method, $subpath);
        break;
    case 'Placement':
        PlacementRoutes($method, $subpath);
        break;
    case 'Faculty':
        FacultyRoutes($method, $subpath);
        break;
    case 'Attendance':
        AttendanceRoutes($method, $subpath);
        break;
    case 'Password':
        PasswordRoutes($method, $subpath);
        break;
    case 'ZoomLink':
        ZooomRoutes($method, $subpath);
        break;
    case 'Holiday':
        HolidayRoutes($method, $subpath);
        break;
    case 'Exam':
        ExamRoutes($method, $subpath);
        break;
    case 'Leave':
        LeaveRoutes($method, $subpath);
        break;
    case 'Event':
        EventRoutes($method, $subpath);
        break;
    case 'InterviewBank':
        InterviewBankRoutes($method, $subpath);
        break;
    case 'Feedback':
        FeedbackRoutes($method, $subpath);
        break;
    case 'Announcement':
        AnnouncementRoutes($method, $subpath);
        break;
    case 'Result':
        ResultRoutes($method, $subpath);
        break;
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Invalid endpoint']);
        break;
}
