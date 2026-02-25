<?php
ob_start();
session_start();

require '../../api/db/db_connection.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

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

// Handle AJAX for subject fetch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_subjects') {
    header('Content-Type: application/json');
    ob_end_clean();

    $sem_id = isset($_POST['sem_id']) ? (int)$_POST['sem_id'] : 0;
    if ($sem_id <= 0) {
        echo json_encode(['success' => false, 'subjects' => [], 'message' => 'Invalid semester ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subject_info WHERE sem_info_id = ?");
    $stmt->bind_param("i", $sem_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subjects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'subjects' => $subjects]);
    exit;
}

// Handle AJAX for exam fetch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_exams') {
    header('Content-Type: application/json');
    ob_end_clean();

    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    if ($subject_id <= 0) {
        echo json_encode(['success' => false, 'exams' => [], 'message' => 'Invalid subject ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, exam_type, exam_date, result_status FROM examination WHERE subject_info_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exams = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'exams' => $exams]);
    exit;
}

// Handle Excel download
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['exam_id'])) {
    $exam_id = (int)$_GET['exam_id'];

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

    // Fetch students
    $stmt = $conn->prepare("SELECT gr_no, CONCAT(first_name, ' ', last_name) AS name, enrollment_no
                            FROM student_info
                            WHERE sem_info_id = ?
                            ORDER BY gr_no");
    $stmt->bind_param("i", $exam['sem_info_id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Create Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);

    // Set headers (B3:E3)
    $sheet->setCellValue('B3', $exam['subject_name'] . ' (' . $exam['subject_code'] . ')');
    $sheet->setCellValue('C3', strtoupper($exam['exam_type']));
    $sheet->setCellValue('D3', date('Y-m-d', strtotime($exam['exam_date'])));
    $sheet->setCellValue('E3', 'SEM ' . $exam['sem'] . ' ' . strtoupper($exam['edu_type']));

    // Style headers (B3:E3)
    $headerStyle = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => '4F81BD'], // Blue
        ],
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FFFFFF'], // White
            'name' => 'Calibri',
            'size' => 11,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];

     // Set default font to Verdana for the whole sheet
     $spreadsheet->getDefaultStyle()->getFont()->setName('Verdana');
     $sheet->setCellValue('A1', 'DO NOT CHANGE THE FORMAT OF SHEET & DO NOT ADD OR REMOVE STUDENT, THAT ACTION THROWS ERROR !');
 
     // Merge the first row (across required columns, adjust 'F' based on number of columns)
     $sheet->mergeCells('A1:P1');
     
     // Apply red background and white font color
     $sheet->getStyle('A1:P1')->applyFromArray([
         'font' => [
             'bold' => true,
             'color' => ['argb' => Color::COLOR_WHITE],
             
         ],
         'fill' => [
             'fillType' => Fill::FILL_SOLID,
             'startColor' => ['argb' => 'FFFF0000'], // Red background
         ],
         'alignment' => [
             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
             'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
         ],
     ]);

    $sheet->getStyle('B3:E3')->applyFromArray($headerStyle);

    // Set column headers (A5:E5)
    $sheet->setCellValue('A5', 'GR No.');
    $sheet->setCellValue('B5', 'Name');
    $sheet->setCellValue('C5', 'Enrollment');
    $sheet->setCellValue('D5', 'Total Marks');
    $sheet->setCellValue('E5', 'Obtains Marks');

    // Style column headers (A5:E5)
    $columnHeaderStyle = [
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'DCE6F1'], // Light gray
        ],
        'font' => [
            'bold' => true,
            'name' => 'Calibri',
            'size' => 11,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A5:E5')->applyFromArray($columnHeaderStyle);

    // Populate student data
    $row = 6;
    foreach ($students as $student) {
        $sheet->setCellValue('A' . $row, $student['gr_no']);
        $sheet->setCellValue('B' . $row, $student['name']);
        $sheet->setCellValue('C' . $row, $student['enrollment_no']);
        // Total Marks and Obtains Marks left empty
        $row++;
    }

    // Style data cells (A6:E6+)
    $dataStyle = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT, // Left for GR No., Name, Enrollment
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
        'font' => [
            'name' => 'Calibri',
            'size' => 11,
        ],
    ];
    $sheet->getStyle('A6:E' . ($row - 1))->applyFromArray($dataStyle);

    // Center-align marks columns
    $sheet->getStyle('D6:E' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Output Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Exam_Result_' . $exam['exam_type'] . '_' . $exam['subject_code'] . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['result_file']) && isset($_POST['exam_id'])) {
    $exam_id = (int)$_POST['exam_id'];
    $file = $_FILES['result_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload failed']);
        exit;
    }

    $upload_dir = '../../Uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_path = $upload_dir . uniqid() . '.xlsx';
    move_uploaded_file($file['tmp_name'], $file_path);

    $_SESSION['result_file'] = $file_path;
    $_SESSION['exam_id'] = $exam_id;

    header('Location: view_result_preview.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exam Results</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #exams-table {
            border-collapse: collapse;
            width: 100%;
        }
        #exams-table th, #exams-table td {
            text-align: center;
            border: 1px solid #d1d5db;
            padding: 8px;
        }
        #exams-table th {
            background-color: #374151;
            color: #ffffff;
        }
        #exams-table tbody tr:hover {
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
        $page_title = "Manage Exam Results";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <h1 class="text-2xl font-bold mb-4">Exam Results</h1>
                <div class="mb-6">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label for="sem_program" class="block text-sm font-medium text-gray-700">Semester & Program</label>
                            <select id="sem_program" class="mt-1 block w-full border rounded p-2" required>
                                <option value="" disabled selected>Select Semester & Program</option>
                                <?php
                                $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY sem, edu_type";
                                $sem_result = mysqli_query($conn, $sem_query);
                                while ($sem_row = mysqli_fetch_assoc($sem_result)) {
                                    echo "<option value='{$sem_row['id']}'>Sem {$sem_row['sem']} - {$sem_row['edu_type']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <select id="subject" class="mt-1 block w-full border rounded p-2" disabled>
                                <option value="" disabled selected>Select Subject</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <table id="exams-table" class="min-w-full bg-white shadow-md rounded-md">
                        <thead>
                            <tr class="bg-gray-700 text-white">
                                <th class="border px-4 py-2 rounded-tl-md">Exam Type</th>
                                <th class="border px-4 py-2">Date</th>
                                <th class="border px-4 py-2">Download List</th>
                                <th class="border px-4 py-2 rounded-tr-md">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            const semSelect = $('#sem_program');
            const subjectSelect = $('#subject');
            const examsTable = $('#exams-table').DataTable({
                scrollX: true,
                paging: false,
                searching: false,
                ordering: true,
                info: false,
                columnDefs: [{ width: '150px', targets: '_all' }]
            });

            // Fetch subjects on semester change
            semSelect.on('change', function() {
                const semId = $(this).val();
                subjectSelect.prop('disabled', true).html('<option value="" disabled selected>Loading...</option>');
                examsTable.clear().draw();

                if (semId) {
                    $.ajax({
                        url: './manage_exam_results.php',
                        method: 'POST',
                        data: { action: 'fetch_subjects', sem_id: semId },
                        dataType: 'json',
                        success: function(response) {
                            subjectSelect.html('<option value="" disabled selected>Select Subject</option>');
                            if (response.success && response.subjects.length > 0) {
                                response.subjects.forEach(subject => {
                                    subjectSelect.append(`<option value="${subject.id}">${subject.subject_name} (${subject.subject_code})</option>`);
                                });
                                subjectSelect.prop('disabled', false);
                            } else {
                                subjectSelect.html('<option value="" disabled selected>No subjects available</option>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Fetch subjects error:', { status: status, error: error, response: xhr.responseText });
                            Swal.fire('Error!', 'Failed to fetch subjects. Check console for details.', 'error');
                        }
                    });
                }
            });

            // Fetch exams on subject change
            subjectSelect.on('change', function() {
                const subjectId = $(this).val();
                examsTable.clear().draw();

                if (subjectId) {
                    $.ajax({
                        url: './manage_exam_results.php',
                        method: 'POST',
                        data: { action: 'fetch_exams', subject_id: subjectId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.exams.length > 0) {
                                response.exams.forEach(exam => {
                                    const action = exam.result_status == 0
                                        ? `<form method="POST" enctype="multipart/form-data" class="upload-form">
                                               <input type="hidden" name="exam_id" value="${exam.id}">
                                               <input type="file" name="result_file" accept=".xlsx" class="hidden" onchange="this.form.submit()">
                                               <button type="button" onclick="this.previousElementSibling.click()" class="bg-cyan-500 text-white p-2 rounded hover:bg-cyan-600">Upload Result</button>
                                           </form>`
                                        : `<a href="view_result_preview.php?exam_id=${exam.id}" class="bg-green-500 text-white p-2 rounded hover:bg-green-600">View Result</a>`;
                                        const action2 = exam.result_status == 0
                                        ? `<a href="?action=download&exam_id=${exam.id}" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Download List</a>`
                                        : `-`;
                                        examsTable.row.add([
                                        exam.exam_type.toUpperCase(),
                                        exam.exam_date,
                                        action2,
                                        action
        
                                    ]).draw();
                                });
                            } else {
                                examsTable.row.add(['', '', '', 'No exams found']).draw();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Fetch exams error:', { status: status, error: error, response: xhr.responseText });
                            Swal.fire('Error!', 'Failed to fetch exams. Check console for details.', 'error');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>