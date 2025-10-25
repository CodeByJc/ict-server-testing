<?php

require_once __DIR__ . '/../controllers/StudentController.php';

function StudentRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'login':
            if ($method === 'POST') {
                StudentLoginController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'logout':
            if ($method === 'POST') {
                StudentLogoutController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'by-enrolment':
            if ($method === 'POST') {
                StudentDetailsController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'branch':
            if ($method === 'GET') {
                GetBatchesByBranchController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        // --- New Section: Social Links ---
        case 'FetchSocialLink':
            if ($method === 'POST') {
                FetchSocialLinksController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddSocialLink':
            if ($method === 'POST') {
                AddSocialLinkController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditSocialLink':
        //     if ($method === 'POST') {
        //         EditSocialLinkController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeleteSocialLink':
            if ($method === 'POST') {
                DeleteSocialLinkController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- New Section: Certificates ---
        case 'FetchCertificates':
            if ($method === 'POST') {
                FetchCertificatesController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddCertificate':
            if ($method === 'POST') {
                AddCertificateController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditCertificate':
        //     if ($method === 'POST') {
        //         EditCertificateController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeleteCertificate':
            if ($method === 'POST') {
                DeleteCertificateController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- New Section: Industry Experience ---
        case 'FetchIndustryExperience':
            if ($method === 'POST') {
                FetchIndustryExperienceController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddIndustryExperience':
            if ($method === 'POST') {
                AddIndustryExperienceController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditIndustryExperience':
        //     if ($method === 'POST') {
        //         EditIndustryExperienceController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeleteIndustryExperience':
            if ($method === 'POST') {
                DeleteIndustryExperienceController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- New Section: Volunteering ---
        case 'FetchVolunteering':
            if ($method === 'POST') {
                FetchVolunteeringController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddVolunteering':
            if ($method === 'POST') {
                AddVolunteeringController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditVolunteering':
        //     if ($method === 'POST') {
        //         EditVolunteeringController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeleteVolunteering':
            if ($method === 'POST') {
                DeleteVolunteeringController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- New Section: Publications ---
        case 'FetchPublications':
            if ($method === 'POST') {
                FetchPublicationsController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddPublication':
            if ($method === 'POST') {
                AddPublicationController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditPublication':
        //     if ($method === 'POST') {
        //         EditPublicationController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeletePublication':
            if ($method === 'POST') {
                DeletePublicationController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- New Section: Patents ---
        case 'FetchPatents':
            if ($method === 'POST') {
                FetchPatentsController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        case 'AddPatent':
            if ($method === 'POST') {
                AddPatentController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // case 'EditPatent':
        //     if ($method === 'POST') {
        //         EditPatentController($input);
        //     } else {
        //         http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
        //     } break;
        case 'DeletePatent':
            if ($method === 'POST') {
                DeletePatentController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;
        // --- End New Section ---

        case 'FetchHonorAward':
            if ($method === 'POST') {
                FetchHonorAwardController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;

        case 'AddHonorAward':
            if ($method === 'POST') {
                AddHonorAwardController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;

        case 'FetchHonorAwards':
            if ($method === 'POST') {
                FetchHonorAwardsController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;

        case 'AddHonorAward':
            if ($method === 'POST') {
                AddHonorAwardController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;

        case 'DeleteHonorAward':
            if ($method === 'POST') {
                DeleteHonorAwardController($input);
            } else {
                http_response_code(405); echo json_encode(['message' => 'Method not allowed']);
            } break;

        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid Student API endpoint']);
            break;
    }
}
?>