<?php
require_once __DIR__ . '/../db/db_connection.php';

/**
 * Logout Student Service
 */
function StudentLogoutService($username) {
    global $conn;

    $username = $conn->real_escape_string($username);
    $update_stmt = $conn->prepare("UPDATE user_login SET device_token = NULL WHERE username = ?");
    if (!$update_stmt) {
        return ['status' => false, 'message' => 'Failed to prepare update statement'];
    }

    $update_stmt->bind_param("s", $username);
    $update_stmt->execute();
    $update_stmt->close();

    return ['status' => true, 'message' => 'User logged out successfully'];
}


/**
 * Login Student Service
 */
function StudentLoginService($username, $password, $device_token) {
    global $conn;

    $username = $conn->real_escape_string($username);
    $stmt = $conn->prepare("SELECT password FROM user_login WHERE username = ?");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare query'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Stored procedure call
    $stmt = $conn->prepare("CALL LoginStudent(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare stored procedure'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();

        $parent_details = json_decode($user_data['parent_details'], true);
        $student_details = json_decode($user_data['student_details'], true);
        $class_details = json_decode($user_data['class_details'], true);

        $full_details = [
            'parent_details' => $parent_details,
            'student_details' => $student_details,
            'class_details' => $class_details,
        ];

        $stmt->close();

        // Update device token
        $update_stmt = $conn->prepare("UPDATE user_login SET device_token = ? WHERE username = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $device_token, $username);
            $update_stmt->execute();
            $update_stmt->close();
        }

        return ['status' => true, 'data' => $full_details];
    }

    $stmt->close();
    return ['status' => false, 'message' => 'Invalid username or password'];
}


function GetSubjectListService($sem_info_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT si.id AS subject_id, si.subject_name, si.subject_code
                            FROM subject_info si
                            WHERE sem_info_id = ?");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare query'];
    }

    $stmt->bind_param("i", $sem_info_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    $stmt->close();
    return ['status' => true, 'data' => ['subjects' => $subjects]];
}

function searchStudentByFaculty($studentId) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetStudentFullProfile(?)");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();

        $data = [
            'profile' => [],
            'social_links' => [],
            'certificates' => [],
            'industry_experience' => [],
            'volunteering' => [],
            'honors_awards' => [],
            'publications' => [],
            'patents' => [],
        ];

        // Result set 1: main profile
        $result = $stmt->get_result();
        $data['profile'] = $result->fetch_assoc();

        // Result set 2: social_links
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['social_links'][] = $row;
            }
        }

        // Result set 3: certificates
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['certificates'][] = $row;
            }
        }

        // Result set 4: industry_experience
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['industry_experience'][] = $row;
            }
        }

        // Result set 5: volunteering
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['volunteering'][] = $row;
            }
        }

        // Result set 6: honors_awards
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['honors_awards'][] = $row;
            }
        }

        // Result set 7: publications
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['publications'][] = $row;
            }
        }

        // Result set 8: patents
        if ($stmt->next_result()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['patents'][] = $row;
            }
        }

        return [
            'status' => true,
            'data' => $data,
            'message' => 'Student data retrieved successfully'
        ];
    } catch (Exception $e) {
        error_log("Error in searchStudentByFaculty: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
function fetchSocialLinksService($input) {
    global $conn;
    // if (!isset($input['studentEnr'])) return ['status'=>false, 'message'=>'username required'];
    $stmt = $conn->prepare("SELECT * FROM sociallinks WHERE username=?");
    $stmt->bind_param("s", $input['username']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addSocialLinkService($input) {
    global $conn;
    $stmt = $conn->prepare("CALL AddSocialLink(?, ?, ?)");
    $stmt->bind_param("sss", $input['username'], $input['type'], $input['url']);
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Social link added'];
}
// function editSocialLinkService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['type'], $input['url'])) return ['status'=>false, 'message'=>'All fields required'];
//     $stmt = $conn->prepare("UPDATE sociallinks SET type=?, url=? WHERE id=?");
//     $stmt->bind_param("ssi", $input['type'], $input['url'], $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Social link updated'];
// }
function deleteSocialLinkService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM sociallinks WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Social link deleted'];
}

// --- New Section: Certificates ---
function fetchCertificatesService($input) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE student_id=?");
    $stmt->bind_param("i", $input['studentId']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addCertificateService($input) {
    global $conn;
    $stmt = $conn->prepare("call AddCertificate(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $input['student_id'], $input['name'], $input['issuing_organization'], $input['issue_month_year'],$input['credential_id'],$input['credential_url']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Certificate added'];
}
// function editCertificateService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['title'], $input['issuer'], $input['date'])) return ['status'=>false, 'message'=>'All fields required'];
//     $stmt = $conn->prepare("UPDATE certificates SET title=?, issuer=?, date=? WHERE id=?");
//     $stmt->bind_param("sssi", $input['title'], $input['issuer'], $input['date'], $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Certificate updated'];
// }
function deleteCertificateService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM certificates WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Certificate deleted'];
}

// --- New Section: Industry Experience ---
function fetchIndustryExperienceService($input) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM industryexperience WHERE student_id=?");
    $stmt->bind_param("i", $input['student_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addIndustryExperienceService($input) {
    global $conn;
    // Prepare to call the procedure
    $stmt = $conn->prepare("CALL AddIndustryExperience(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "issssssssss",
        $input['student_id'],
        $input['title'],
        $input['employment_type'],
        $input['company_name'],
        $input['start_month_year'],
        $input['end_month_year'],
        $input['currently_working'],
        $input['location'],
        $input['location_type'],
        $input['description'],
        $input['media_url']
    );
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Industry Experience added'];
}
// function editIndustryExperienceService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['company'], $input['role'], $input['start'])) return ['status'=>false, 'message'=>'All fields required'];
//     $end = $input['end'] ?? '';
//     $stmt = $conn->prepare("UPDATE industryexperience SET company=?, role=?, start=?, end=? WHERE id=?");
//     $stmt->bind_param("ssssi", $input['company'], $input['role'], $input['start'], $end, $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Industry experience updated'];
// }

function deleteIndustryExperienceService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM industryexperience WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Industry experience deleted'];
}

// --- New Section: Volunteering ---
function fetchVolunteeringService($input) {
    global $conn;
    if (!isset($input['student_id'])) return ['status'=>false, 'message'=>'studentId required'];
    $stmt = $conn->prepare("SELECT * FROM volunteering WHERE student_id=?");
    $stmt->bind_param("i", $input['student_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addVolunteeringService($input) {
    global $conn;
    // Prepare to call the procedure
    $stmt = $conn->prepare("CALL AddVolunteering(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "issssssss",
        $input['student_id'],
        $input['organization'],
        $input['role'],
        $input['cause'],
        $input['start_month_year'],
        $input['end_month_year'],
        $input['currently_volunteering'],
        $input['description'],
        $input['media_url']
    );
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Volunteering added'];
}
// function editVolunteeringService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['organization'], $input['role'], $input['start'])) return ['status'=>false, 'message'=>'All fields required'];
//     $end = $input['end'] ?? '';
//     $stmt = $conn->prepare("UPDATE student_volunteering SET organization=?, role=?, start=?, end=? WHERE id=?");
//     $stmt->bind_param("ssssi", $input['organization'], $input['role'], $input['start'], $end, $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Volunteering updated'];
// }
function deleteVolunteeringService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM volunteering WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Volunteering deleted'];
}

// --- New Section: Publications ---
function fetchPublicationsService($input) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM publications WHERE student_id=?");
    $stmt->bind_param("i", $input['student_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addPublicationService($input) {
    global $conn;
    $stmt = $conn->prepare("CALL AddPublication(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssss",
        $input['student_id'],
        $input['title'],
        $input['publisher'],
        $input['publication_date'],
        $input['publication_url'],
        $input['description']
    );
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Publication added'];
}
// function editPublicationService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['title'], $input['journal'], $input['date'])) return ['status'=>false, 'message'=>'All fields required'];
//     $stmt = $conn->prepare("UPDATE student_publications SET title=?, journal=?, date=? WHERE id=?");
//     $stmt->bind_param("sssi", $input['title'], $input['journal'], $input['date'], $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Publication updated'];
// }
function deletePublicationService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM publications WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Publication deleted'];
}

// --- New Section: Patents ---
function fetchPatentsService($input) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM patents WHERE student_id=?");
    $stmt->bind_param("i", $input['student_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}
function addPatentService($input) {
    global $conn;
    $stmt = $conn->prepare("CALL AddPatent(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssssss",
        $input['student_id'],
        $input['patent_title'],
        $input['patent_number'],
        $input['inventor_code'],
        $input['status'],
        $input['issue_date'],
        $input['patent_url'],
        $input['description']
    );
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Patent added'];
}
// function editPatentService($input) {
//     global $conn;
//     if (!isset($input['id'], $input['title'], $input['patent_no'], $input['date'])) return ['status'=>false, 'message'=>'All fields required'];
//     $stmt = $conn->prepare("UPDATE student_patents SET title=?, patent_no=?, date=? WHERE id=?");
//     $stmt->bind_param("sssi", $input['title'], $input['patent_no'], $input['date'], $input['id']);
//     $stmt->execute();
//     return ['status'=>true, 'message'=>'Patent updated'];
// }
function deletePatentService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM patents WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Patent deleted'];
}

function fetchHonorAwardsService($input) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM honorsawards WHERE student_id=?");
    $stmt->bind_param("i", $input['student_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['status'=>true, 'data'=>$result];
}

function addHonorAwardService($input) {
    global $conn;
    $stmt = $conn->prepare("CALL AddHonorAward(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssss",
        $input['student_id'],
        $input['title'],
        $input['issuer'],
        $input['issue_month_year'],
        $input['description'],
        $input['media_url']
    );
    $stmt->execute();
    $stmt->close();
    return ['status'=>true, 'message'=>'Honor/Award added'];
}

function deleteHonorAwardService($input) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM honorsawards WHERE id=?");
    $stmt->bind_param("i", $input['id']);
    $stmt->execute();
    return ['status'=>true, 'message'=>'Honor/Award deleted'];
}
?>
