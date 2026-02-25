<?php
require '../../api/db/db_connection.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

$sql = "SELECT id,sem,edu_type FROM sem_info";
$result = $conn->query($sql);

try {
    // Function to increment column letters properly, even after Z (e.g., to AA, AB)
function incrementColumn($col, $step = 1) {
    for ($i = 0; $i < $step; $i++) {
        $col++;
        if (strlen($col) > 1 && $col[1] > 'Z') {
            $col = chr(ord($col[0]) + 1) . 'A';
        } elseif ($col > 'Z') {
            $col = 'A' . $col[0];
        }
    }
    return $col;
}

$subjectHeadingRow = 3;
$TLheadingRow = $subjectHeadingRow+1;
$otherHeadingRow = $TLheadingRow+1;
$studentDataStartRow=$otherHeadingRow+1;
$sem = '';
$edu_type = '';
$semId = '';


// When the "Download" button is clicked
if (isset($_POST['download'])) {
    
    $semId = $_POST['semId'];
    $query = "SELECT sem, edu_type FROM sem_info WHERE id = $semId";
    $res = $conn->query($query);
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $sem = $row['sem'];
        $edu_type = $row['edu_type'];
    }

    // Create a new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

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

    $sheet->getStyle('D:AZ')->applyFromArray([
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ]);
    
    // Adjust row height for the message
    $sheet->getRowDimension('1')->setRowHeight(20);
    $sheet->getColumnDimension('B')->setWidth(25);  
    $sheet->getColumnDimension('C')->setWidth(12); // Instead of setColumnWidth()

    // Set headers for Student Info
    $sheet->setCellValue('A'.$otherHeadingRow, 'GR No.');
    $sheet->setCellValue('B'.$otherHeadingRow, 'Name');
    $sheet->setCellValue('C'.$otherHeadingRow, 'Enrollment');
    // Set column width for columns D to AZ


    // Apply background color and alignment to Student Info header cells
    $studentHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'B8CCE4'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A'.$otherHeadingRow.':C'.$otherHeadingRow)->applyFromArray($studentHeaderStyle);

    // Fetch subjects for semester 7 (degree)
    $subjectQuery = "
        SELECT subject_info.short_name, subject_info.lec_type
        FROM subject_info
        JOIN sem_info ON subject_info.sem_info_id = sem_info.id
        WHERE sem_info.sem = $sem AND sem_info.edu_type = '$edu_type'
        order by subject_info.subject_name,subject_info.lec_type
    ";

    $subjectResult = mysqli_query($conn, $subjectQuery);
    $col = 'D'; // Starting column for subjects
    while ($subjectRow = mysqli_fetch_assoc($subjectResult)) {
        $lecType = $subjectRow['lec_type'];
        $shortName = $subjectRow['short_name'];
        
        if ($lecType == 'TL') {
            
            // Merge cells for subject name across four columns (two for L, two for T)
            $sheet->mergeCells($col . $subjectHeadingRow.':' . incrementColumn($col, 3) . $subjectHeadingRow);
            $sheet->setCellValue($col . $subjectHeadingRow, $shortName); // Subject header (row 1)
            $sheet->getStyle($col . $subjectHeadingRow.':' . incrementColumn($col, 3) . $subjectHeadingRow+1)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9933']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
        ],
            ]);

            // Add L and T in row 2
            $sheet->setCellValue($col . $TLheadingRow , 'L');
            $sheet->mergeCells($col . $TLheadingRow .':' . incrementColumn($col, 1) . $TLheadingRow);
            
            $tCol = incrementColumn($col, 2); // Set pointer for T columns
            $sheet->setCellValue($tCol . $TLheadingRow , 'T');
            $sheet->mergeCells($tCol . $TLheadingRow .':' . incrementColumn($tCol, 1) . $TLheadingRow );
        
            // Add formatting for L and T
            $sheet->getStyle($col . $TLheadingRow .':' . incrementColumn($tCol, 1) . $TLheadingRow )->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '92CDDC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            // Add total and attend under L and T in row 3
            // L Section
            $sheet->setCellValue($col . $otherHeadingRow, 'total'); // total under L
            $sheet->setCellValue(incrementColumn($col) . $otherHeadingRow, 'attend'); // attend under L
        
            // T Section
            $sheet->setCellValue($tCol . $otherHeadingRow, 'total'); // total under T
            $sheet->setCellValue(incrementColumn($tCol) . $otherHeadingRow, 'attend'); // attend under T
        
            // Add formatting for total and attend
            $sheet->getStyle($col . $otherHeadingRow.':' . incrementColumn($tCol, 1) . $otherHeadingRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D8E4BC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            $col = incrementColumn($tCol, 2);
        }
        
         elseif ($lecType == 'L') {
            // Merge cells for subject name across two columns (total and attend for L)
            $sheet->mergeCells($col . $subjectHeadingRow.':' . incrementColumn($col, 1) . $subjectHeadingRow);
            $sheet->setCellValue($col . $subjectHeadingRow, $shortName); // Subject header (row 1)
            $sheet->getStyle($col . $subjectHeadingRow.':' . incrementColumn($col) . $subjectHeadingRow+1)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9933']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
        ],
            ]);

            // Add L in row 2
            $sheet->mergeCells($col . $TLheadingRow .':' . incrementColumn($col, 1) . $TLheadingRow );
            $sheet->setCellValue($col . $TLheadingRow , 'L');
            $sheet->getStyle($col . $TLheadingRow .':' . incrementColumn($col, 1) . $TLheadingRow )->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '92CDDC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            // Add total and attend in row 3
            $sheet->setCellValue($col . $otherHeadingRow, 'total'); // total under L
            $sheet->setCellValue(incrementColumn($col) . $otherHeadingRow, 'attend'); // attend under L
            $sheet->getStyle($col . $otherHeadingRow.':' . incrementColumn($col, 1) . $otherHeadingRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D8E4BC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            // Move the column pointer ahead by 2
            $col = incrementColumn($col, 2);
        } elseif ($lecType == 'T') {
            // Merge cells for subject name across two columns (total and attend for T)
            $sheet->mergeCells($col . $subjectHeadingRow.':' . incrementColumn($col, 1) . $subjectHeadingRow);
            $sheet->setCellValue($col . $subjectHeadingRow, $shortName); // Subject header (row 1)
            $sheet->getStyle($col . $subjectHeadingRow.':' . incrementColumn($col) . $subjectHeadingRow+1)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9933']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                'left' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
                'right' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
        ],
            ]);

            // Add T in row 2
            $sheet->mergeCells($col . $TLheadingRow.':' . incrementColumn($col, 1) . $TLheadingRow);
            $sheet->setCellValue($col . $TLheadingRow, 'T');
            $sheet->getStyle($col . $TLheadingRow.':' . incrementColumn($col, 1) . $TLheadingRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '92CDDC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            // Add total and attend in row 3
            $sheet->setCellValue($col . $otherHeadingRow, 'total'); // total under T
            $sheet->setCellValue(incrementColumn($col) . $otherHeadingRow, 'attend'); // attend under T
            $sheet->getStyle($col . $otherHeadingRow.':' . incrementColumn($col, 1) . $otherHeadingRow)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D8E4BC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
            'vertical' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
            ]);

            // Move the column pointer ahead by 2
            $col = incrementColumn($col, 2);
        }
    }

    // Fetch students in semester 7 (degree)
    $studentQuery = "
    SELECT student_info.id,student_info.first_name, student_info.last_name, student_info.enrollment_no, student_info.gr_no 
    FROM student_info 
    JOIN sem_info ON student_info.sem_info_id = sem_info.id
    WHERE sem_info.sem = $sem AND sem_info.edu_type = '$edu_type'
    ORDER BY student_info.gr_no
    ";
$studentResult = mysqli_query($conn, $studentQuery);

// Add student details and leave subject columns empty initially
$row = $studentDataStartRow; // Starting row for student data

while ($studentRow = mysqli_fetch_assoc($studentResult)) {

    $p_student_id=$studentRow['id'];

    $totalAttendance = mysqli_query($conn, 
"SELECT
		si.id,
        si.lec_type as sub_lec_type,
        si.subject_name,
        si.short_name,
        'T' AS lec_type,
        COALESCE(tai.total, 0) AS total_lec,
        COALESCE(tai.attend, 0) AS attend_lec
    FROM student_info s
    JOIN class_info ci ON s.class_info_id = ci.id
    JOIN sem_info sm ON ci.sem_info_id = sm.id
    JOIN subject_info si ON ci.sem_info_id = si.sem_info_id
    LEFT JOIN total_attendance_info tai ON si.id = tai.subject_info_id 
        AND tai.student_info_id = $p_student_id AND tai.lec_type='T' LEFT JOIN elective_allocation ea ON si.id = ea.subject_info_id 
        AND ea.student_info_id = $p_student_id
    WHERE s.id = $p_student_id
    AND (
        si.type = 'mandatory' OR 
        (si.type = 'elective' AND ea.student_info_id IS NOT NULL)
    )
    AND si.lec_type IN ('T', 'TL')  -- Only include T-type lectures or TL-type
    GROUP BY si.subject_name, lec_type

    UNION ALL

    -- Now, get the attendance for L-type lectures
    SELECT 
        si.id,
        si.lec_type as sub_lec_type,
        si.subject_name,
        si.short_name,
        'L' AS lec_type,
        COALESCE(tai.total, 0) AS total_lec,
        COALESCE(tai.attend, 0) AS attend_lec
    FROM student_info s
    JOIN class_info ci ON s.class_info_id = ci.id
    JOIN sem_info sm ON ci.sem_info_id = sm.id
    JOIN subject_info si ON ci.sem_info_id = si.sem_info_id
    LEFT JOIN total_attendance_info tai ON si.id = tai.subject_info_id 
        AND tai.student_info_id = $p_student_id AND tai.lec_type='L'
    LEFT JOIN elective_allocation ea ON si.id = ea.subject_info_id 
        AND ea.student_info_id = $p_student_id
    WHERE s.id = $p_student_id
    AND (
        si.type = 'mandatory' OR 
        (si.type = 'elective' AND ea.student_info_id IS NOT NULL)
    )
    AND si.lec_type IN ('L', 'TL')  -- Only include L-type lectures or TL-type
    GROUP BY si.subject_name, lec_type

    -- Correcting the sorting by using column aliases
    ORDER BY subject_name, lec_type;
"
);
    
    // Fetch the results into an array
    $attendanceData = [];
    while ($attendanceRow = mysqli_fetch_assoc($totalAttendance)) {
        $attendanceData[] = $attendanceRow; // Store each attendance record in an array
    }
    
    // Set student details
    $sheet->setCellValue('A' . $row, $studentRow['gr_no']);
    $sheet->setCellValue('B' . $row, $studentRow['first_name'] . ' ' . $studentRow['last_name']);
    $sheet->setCellValue('C' . $row, $studentRow['enrollment_no']);


    $col = 'D';
    foreach ($attendanceData as $attendance) {
        $shortName = $attendance['short_name'];
        $sub_lec_type = $attendance['sub_lec_type'];
        $lecType = $attendance['lec_type'];
        $totalLec = $attendance['total_lec'];
        $attendLec = $attendance['attend_lec'];

        while($sheet->getCell($col . $TLheadingRow)->getValue() != '') 
         {
            $subjectHeader = $sheet->getCell($col . $subjectHeadingRow)->getValue();
          
            if ($subjectHeader == $shortName) {
               if($sub_lec_type=="TL")
                {
                    if($lecType=="L")
                    {
                        $sheet->setCellValue($col . $row, $totalLec); // Total for T
                        $sheet->setCellValue(incrementColumn($col) . $row, $attendLec); // Attend for T
                        
                    }
                    elseif($lecType=="T"){
                        $col = incrementColumn($col,2);
                        $sheet->setCellValue($col . $row, $totalLec); // Total for T
                        $sheet->setCellValue(incrementColumn($col) . $row, $attendLec); // Attend for T
                        $col = incrementColumn($col,2);
                    }
                    break;
                }
                elseif($lecType=="L" || $lecType=="T")
                {
                        $sheet->setCellValue($col . $row, $totalLec); // Total for T
                        $sheet->setCellValue(incrementColumn($col) . $row, $attendLec); // Attend for T
;
                        $col = incrementColumn($col,2);
                        break;
                }
            }
            $col = incrementColumn($col, 2); // Move to next subject
        }
    }
    $row++; // Move to the next student
}


// Apply border to the subject headers and the subject columns
$sheet->getStyle('A'.$otherHeadingRow.':' . incrementColumn($col, -2) . $otherHeadingRow)->applyFromArray([
    'borders' => [
        'vertical' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
]);

// Apply border to the rows under subject columns (total and attend)
$sheet->getStyle('A5:' . incrementColumn($col,2) . ($row - 1))->applyFromArray([
    'borders' => [
        'vertical' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
]);

    // At the end, save the Excel file
    $writer = new Xlsx($spreadsheet);
    $fileName = $edu_type.'_Sem'.$sem.'_Attendance_('. date('d-m-Y') .').xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit; // Stop further execution
}
} catch (Exception $e) {
    echo 'Error: ',  $e->getMessage();
}
$conn->close();
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total Attendance Upload</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- SweetAlert library -->
</head>
<body class="bg-gray-100 text-gray-800 flex">
    
    <?php include('./sidebar.php'); ?>

    <!-- Centered Card Container -->
    <div class="flex-1  pl-64  items-center justify-center">
    <?php 
    $page_title = "Students Total Attendance";
    include('./navbar.php'); 
    ?>
        <div class="p-12">
        <div class="bg-white shadow-lg rounded-2xl p-10 mb-4 max-w-xl w-full">
            <!-- Form 1 -->
            <form method="post">
            <div class="mb-4">
    <label for="semId" class="block text-gray-700 font-bold mb-2">Select Semester:</label>
    <select name="semId" id="semId" class="block w-full border-2 rounded py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <?php
        if (!isset($result)) {
            echo "<option value=''>Error: Result variable not defined</option>";
            echo "<script>console.log('Error: Result variable not defined');</script>";
        } elseif ($result->num_rows > 0) {
            echo "<script>console.log('Rows found: " . $result->num_rows . "');</script>";
            // Output data for each row
            while ($row = $result->fetch_assoc()) {
                // Print row data to console
                $rowData = json_encode($row); // Safely encode row data for JavaScript
                echo "<script>console.log('Row data: ', " . $rowData . ");</script>";
                echo "<option value='" . $row['id'] . "'>Sem - " . $row['sem'] . " - " . $row['edu_type'] . "</option>";
            }
        } else {
            echo "<option value=''>No data available</option>";
            echo "<script>console.log('No rows found');</script>";
        }
        ?>
    </select>
</div>
                <button type="submit" name="download" class="bg-blue-500 text-white font-bold py-2 px-10 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                    Download Sheet
                </button>
            </form>
           
        </div>

        <div class="bg-white shadow-lg rounded-2xl p-10 mb-4 max-w-xl w-full">
            <?php include('./UploadTotalAttendance.php'); ?>
        </div>
        </div>

    </div>

</body>
</html>
