<?php
use function JmesPath\search;

require_once __DIR__ . '/../services/StudentService.php';

/**
 * Student Login Controller
 */
function StudentLoginController($input) {

    error_log("Received Login Request: " . json_encode($input));

    if (!isset($input['username']) || !isset($input['password']) || !isset($input['device_token'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username, password and device_token are required']);
        return;
    }

    $username = $input['username'];
    $password = $input['password'];
    $device_token = $input['device_token'];

    $response = StudentLoginService($username, $password, $device_token);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(401);
        echo json_encode(['message' => $response['message']]);
    }

    error_log("Login Response: " . json_encode($response));
}


/**
 * Student Logout Controller
 */
function StudentLogoutController($input) {
    if (!isset($input['username'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username required']);
        return;
    }

    $username = $input['username'];
    $response = StudentLogoutService($username);

    if ($response['status']) {
        echo json_encode(['message' => $response['message']]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => $response['message']]);
    }
}


/**
 * Search Student Details by Faculty
 */
function StudentDetailsController($input) {
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'student_id required']);
        return;
    }

    $enrolment = $input['student_id'];
    $response = searchStudentByFaculty($enrolment);

    if ($response['status']) {
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['message' => $response['message']]);
    }
}

function FetchSocialLinksController($input) {
    // Accepts 'username' from payload and maps to service's expected 'studentEnr'
    if (!isset($input['username'])) {
        echo json_encode(['status'=>false, 'message'=>'username required']);
        return;
    }
    // Map 'username' to 'studentEnr' for service compatibility
    $input['studentEnr'] = $input['username'];
    $response = fetchSocialLinksService($input);
    echo json_encode($response);
}

function AddSocialLinkController($input) {
    // Accepts 'username', 'type', and 'url' from payload and passes directly to the service
    // Optionally, you can validate here if needed:
    if (!isset($input['username'], $input['type'], $input['url'])) {
        echo json_encode(['status'=>false, 'message'=>'All fields required']);
        return;
    }
    $response = addSocialLinkService($input);
    echo json_encode($response);
}
// function EditSocialLinkController($input) {
//     $response = editSocialLinkService($input);
//     echo json_encode($response);
// }

function DeleteSocialLinkController($input) {
    // Validate required field
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deleteSocialLinkService($input);
    echo json_encode($response);
}

// --- New Section: Certificates ---
function FetchCertificatesController($input) {
    if (!isset($input['studentId'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchCertificatesService($input);
    echo json_encode($response);
}

function AddCertificateController($input) {
    if (!isset($input['student_id'], $input['name'], $input['issuing_organization'], $input['credential_id'],$input['credential_url'],$input['issue_month_year'])){
        echo json_encode(['status'=>false, 'message'=>'All fields required']);
        return;
    }
    $response = addCertificateService($input);
    echo json_encode($response);
}

// function EditCertificateController($input) {
//     $response = editCertificateService($input);
//     echo json_encode($response);
// }

function DeleteCertificateController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deleteCertificateService($input);
    echo json_encode($response);
}

// --- New Section: Industry Experience ---
function FetchIndustryExperienceController($input) {
    if (!isset($input['student_id'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchIndustryExperienceService($input);
    echo json_encode($response);
}

function AddIndustryExperienceController($input) {
    // Validate required fields
    $required = [
        'student_id', 'title', 'employment_type', 'company_name',
        'start_month_year', 'currently_working', 'location',
        'location_type', 'description', 'media_url'
    ];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            echo json_encode(['status'=>false, 'message'=>"Missing field: $field"]);
            return;
        }
    }

    // Logic: if currently_working == 'yes', set end_month_year = null
    if ($input['currently_working'] === 'yes') {
        $input['end_month_year'] = null;
    } else {
        // If 'no', use value or null
        $input['end_month_year'] = !empty($input['end_month_year']) ? $input['end_month_year'] : null;
    }

    // Call the service
    $response = addIndustryExperienceService($input);
    echo json_encode($response);
}
// function EditIndustryExperienceController($input) {
//     $response = editIndustryExperienceService($input);
//     echo json_encode($response);
// }

function DeleteIndustryExperienceController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deleteIndustryExperienceService($input);
    echo json_encode($response);
}

// --- New Section: Volunteering ---
function FetchVolunteeringController($input){
    if (!isset($input['student_id'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchVolunteeringService($input);
    echo json_encode($response);
}

function AddVolunteeringController($input) {
    // Validate required fields
    $required = [
        'student_id', 'organization', 'role', 'cause', 
        'start_month_year', 'currently_volunteering', 
        'description', 'media_url'
    ];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['status'=>false, 'message'=>"Missing field: $field"]);
            return;
        }
    }

    // Handle end_month_year logic
    if ($input['currently_volunteering'] === 'yes') {
        $input['end_month_year'] = null;
    } else {
        // If 'no', use value or null
        $input['end_month_year'] = !empty($input['end_month_year']) ? $input['end_month_year'] : null;
    }

    // Call the service (expects all fields in $input)
    $response = addVolunteeringService($input);
    echo json_encode($response);
}
// function EditVolunteeringController($input) {
//     $response = editVolunteeringService($input);
//     echo json_encode($response);
// }

function DeleteVolunteeringController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deleteVolunteeringService($input);
    echo json_encode($response);
}

// --- New Section: Publications ---
function FetchPublicationsController($input) {
    if (!isset($input['student_id'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchPublicationsService($input);
    echo json_encode($response);
}

function AddPublicationController($input) {
    // Validate required fields
    $required = [
        'student_id', 'title', 'publisher', 'publication_date', 'publication_url', 'description'
    ];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['status'=>false, 'message'=>"Missing field: $field"]);
            return;
        }
    }
    $response = addPublicationService($input);
    echo json_encode($response);
}
// function EditPublicationController($input) {
//     $response = editPublicationService($input);
//     echo json_encode($response);
// }

function DeletePublicationController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deletePublicationService($input);
    echo json_encode($response);
}

// --- New Section: Patents ---
function FetchPatentsController($input) {
    if (!isset($input['student_id'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchPatentsService($input);
    echo json_encode($response);
}

function AddPatentController($input) {
    // Validate required fields
    $required = [
        'student_id', 'patent_title', 'patent_number', 'inventor_code',
        'status', 'issue_date', 'patent_url', 'description'
    ];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['status'=>false, 'message'=>"Missing field: $field"]);
            return;
        }
    }
    $response = addPatentService($input);
    echo json_encode($response);
}

// function EditPatentController($input) {
//     $response = editPatentService($input);
//     echo json_encode($response);
// }

function DeletePatentController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deletePatentService($input);
    echo json_encode($response);
}


function FetchHonorAwardsController($input) {
    if (!isset($input['student_id'])){
        echo json_encode(['status'=>false, 'message'=>'studentId required']);
        return;
    }
    $response = fetchHonorAwardsService($input);
    echo json_encode($response);
}

function AddHonorAwardController($input) {
    // Validate required fields
    $required = [
        'student_id', 'title', 'issuer', 'issue_month_year', 'description', 'media_url'
    ];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['status'=>false, 'message'=>"Missing field: $field"]);
            return;
        }
    }
    $response = addHonorAwardService($input);
    echo json_encode($response);
}
function DeleteHonorAwardController($input) {
    if (!isset($input['id'])) {
        echo json_encode(['status'=>false, 'message'=>'id required']);
        return;
    }
    $response = deleteHonorAwardService($input);
    echo json_encode($response);
}
?>
