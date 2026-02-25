<?php
include('../../api/db/db_connection.php');
$id = $_POST['id'];

$response = ['status' => 'success', 'message' => ''];

try {
    $conn->begin_transaction();

    $query = "DELETE FROM time_table WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $conn->commit();
    $response['message'] = 'Slot deleted successfully';
} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Error deleting slot: ' . $e->getMessage()];
}

echo json_encode($response);
?>