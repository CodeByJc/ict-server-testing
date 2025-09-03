<?php
ob_start();
session_start();

require '../../api/db/db_connection.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'] ?? [];
$faculty_info_id = $userdata['id'] ?? 0;

// Check database connection
if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}

// Handle result preview or view
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : (isset($_SESSION['exam_id']) ? (int)$_SESSION['exam_id'] : 0);
$file_path = isset($_SESSION['result_file']) ? $_SESSION['result_file'] : null;
$is_preview = $file_path !== null;

if (!$exam_id) {
    die("Invalid exam ID");
}

// Fetch exam details
$stmt = $conn->prepare("SELECT e.exam_type, e.exam_date, e.sem_info_id, e.subject_info_id,
                        s.subject_name, s.subject_code, si.sem, si.edu_type
                        FROM examination e
                        JOIN subject_info s ON e.subject_info_id = s.id
                        JOIN sem_info si ON e.sem_info_id = si.id
                        WHERE e.id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die("Exam not found");
}

// Grade calculation
function calculate_grade($obtain_marks, $total_marks) {
    if (is_null($obtain_marks) || $obtain_marks === '') {
        return 'Ab';
    }
    $percentage = ($obtain_marks / $total_marks) * 100;
    if ($percentage >= 90) return 'O';
    if ($percentage >= 80) return 'A+';
    if ($percentage >= 70) return 'A';
    if ($percentage >= 60) return 'B+';
    if ($percentage >= 50) return 'B';
    if ($percentage >= 45) return 'C';
    if ($percentage >= 40) return 'D';
    return 'F';
}

// Load results
$results = [];
if ($is_preview) {
    // Read uploaded Excel
    $spreadsheet = IOFactory::load($file_path);
    $sheet = $spreadsheet->getActiveSheet();
    $row = 6;
    while ($sheet->getCell('A' . $row)->getValue() !== null) {
        $gr_no = $sheet->getCell('A' . $row)->getValue();
        $name = $sheet->getCell('B' . $row)->getValue();
        $enrollment_no = $sheet->getCell('C' . $row)->getValue();
        $total_marks = $sheet->getCell('D' . $row)->getValue();
        $obtain_marks = $sheet->getCell('E' . $row)->getValue();

        // Fetch student_info_id
        $stmt = $conn->prepare("SELECT id FROM student_info WHERE gr_no = ? AND sem_info_id = ?");
        $stmt->bind_param("si", $gr_no, $exam['sem_info_id']);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student) {
            $grade = calculate_grade($obtain_marks, $total_marks);
            $results[] = [
                'student_info_id' => $student['id'],
                'gr_no' => $gr_no,
                'name' => $name,
                'enrollment_no' => $enrollment_no,
                'total_marks' => $total_marks,
                'obtain_marks' => $obtain_marks,
                'grade' => $grade
            ];
        }
        $row++;
    }
} else {
    // Fetch from exam_result_info
    $stmt = $conn->prepare("SELECT eri.student_info_id, eri.total_marks, eri.obtain_marks, eri.grade,
                            si.gr_no, CONCAT(si.first_name, ' ', si.last_name) AS name, si.enrollment_no
                            FROM exam_result_info eri
                            JOIN student_info si ON eri.student_info_id = si.id
                            WHERE eri.exam_info_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle save results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_results') {
    header('Content-Type: application/json');
    ob_end_clean();

    $results = json_decode($_POST['results'], true);
    if (empty($results)) {
        echo json_encode(['success' => false, 'message' => 'No results to save']);
        exit;
    }

    $conn->begin_transaction();
    try {
        foreach ($results as $result) {
            $stmt = $conn->prepare("INSERT INTO exam_result_info (student_info_id, exam_info_id, total_marks, obtain_marks, grade)
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiids", $result['student_info_id'], $exam_id, $result['total_marks'], $result['obtain_marks'], $result['grade']);
            $stmt->execute();
            $stmt->close();
        }

        // Update result_status
        $stmt = $conn->prepare("UPDATE examination SET result_status = 1 WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        unlink($file_path); // Delete temp file
        unset($_SESSION['result_file'], $_SESSION['exam_id']);
        echo json_encode(['success' => true, 'message' => 'Results saved successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to save results: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Preview</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #results-table {
            border-collapse: collapse;
            width: 100%;
        }
        #results-table th, #results-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            padding: 8px;
        }
        #results-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #results-table tbody tr:hover {
            background-color: #e5e7eb;
        }
        .table-container {
            overflow-x: auto;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 overflow-y-auto">
        <?php
        $page_title = $is_preview ? "Result Preview" : "View Results";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <h1 class="text-2xl font-bold mb-4"><?php echo $is_preview ? 'Result Preview' : 'Exam Results'; ?></h1>
                <div class="mb-4">
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject_name'] . ' (' . $exam['subject_code'] . ')'); ?></p>
                    <p><strong>Exam Type:</strong> <?php echo strtoupper($exam['exam_type']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?></p>
                    <p><strong>Semester:</strong> Sem <?php echo $exam['sem'] . ' - ' . $exam['edu_type']; ?></p>
                </div>
                <div class="table-container">
                    <table id="results-table" class="min-w-full bg-white shadow-md rounded-md">
                        <thead>
                            <tr class="bg-gray-700 text-white">
                                <th class="border px-4 py-2 rounded-tl-md">GR No.</th>
                                <th class="border px-4 py-2">Name</th>
                                <th class="border px-4 py-2">Enrollment</th>
                                <th class="border px-4 py-2">Total Marks</th>
                                <th class="border px-4 py-2">Obtain Marks</th>
                                <th class="border px-4 py-2 rounded-tr-md">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['gr_no']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['name']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['enrollment_no']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['total_marks']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['obtain_marks'] ?: ''); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($result['grade']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($is_preview): ?>
                    <div class="mt-6 flex justify-end">
                        <button id="confirm-btn" class="bg-cyan-500 text-white p-2 px-6 rounded-full hover:bg-cyan-600">Confirm & Save</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('#results-table').DataTable({
                scrollX: true,
                paging: false,
                searching: false,
                ordering: true,
                info: false,
                columnDefs: [{ width: '150px', targets: '_all' }]
            });

            <?php if ($is_preview): ?>
                $('#confirm-btn').on('click', function() {
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'Once saved, these results cannot be modified. Confirm?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#06b6d4',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, save results!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: './view_result_preview.php',
                                method: 'POST',
                                data: {
                                    action: 'save_results',
                                    results: JSON.stringify(<?php echo json_encode($results); ?>)
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire('Success!', response.message, 'success').then(() => {
                                            window.location.href = './manage_exam_results.php';
                                        });
                                    } else {
                                        Swal.fire('Error!', response.message, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('Error!', 'Failed to save results.', 'error');
                                }
                            });
                        }
                    });
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>