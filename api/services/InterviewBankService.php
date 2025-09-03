<?php
require_once __DIR__ . '/../db/db_connection.php';

function createInterviewBankService($input) {
    global $conn;

    $studentId = $input['student_info_id'] ?? null;
    $companyId = $input['company_info_id'] ?? null;
    $date = $input['date'] ?? null;
    $data = $input['data'] ?? null;

    if (!$studentId || !$companyId || !$date || !$data) {
        return ['status' => false, 'message' => 'All fields are required'];
    }

    $stmt = $conn->prepare("INSERT INTO interview_bank (student_info_id, company_info_id, date, data) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $studentId, $companyId, $date, $data);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Interview bank entry created successfully'];
    }

    return ['status' => false, 'message' => 'Failed to create interview bank entry'];
}

function getAllInterviewBankService() {
    global $conn;
    $result = $conn->query("SELECT ib.*,ci.company_name,ci.company_type,concat(si.first_name,' ',si.last_name) as student_name FROM interview_bank ib	
JOIN company_info ci ON ib.company_info_id = ci.id
JOIN student_info si ON ib.student_info_id = si.id
ORDER BY date DESC");
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    return ['status' => true, 'data' => $data];
}

function getInterviewBankByIdService($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM interview_bank WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();
    return $result ? ['status' => true, 'data' => $result] : ['status' => false, 'message' => 'Entry not found'];
}

function updateInterviewBankService($id, $input) {
    global $conn;

    $studentId = $input['student_info_id'] ?? null;
    $companyId = $input['company_info_id'] ?? null;
    $date = $input['date'] ?? null;
    $data = $input['data'] ?? null;

    if (!$studentId || !$companyId || !$date || !$data) {
        return ['status' => false, 'message' => 'All fields are required'];
    }

    $stmt = $conn->prepare("UPDATE interview_bank SET student_info_id = ?, company_info_id = ?, date = ?, data = ? WHERE id = ?");
    $stmt->bind_param("iissi", $studentId, $companyId, $date, $data, $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Interview bank entry updated'];
    }

    return ['status' => false, 'message' => 'Update failed'];
}

function deleteInterviewBankService($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM interview_bank WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Entry deleted'];
    }

    return ['status' => false, 'message' => 'Delete failed'];
}
