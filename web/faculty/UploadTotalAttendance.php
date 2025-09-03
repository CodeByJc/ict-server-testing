
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <title>Upload Total Attendance</title>

</head>
<?php
require '../../api/db/db_connection.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Function to get all students (store in associative array for quick lookup)
function getAllStudents($conn) {
    $students = [];
    $sql = "SELECT id, gr_no FROM student_info";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $students[$row['gr_no']] = $row['id'];
    }
    return $students;
}

// Function to get all subjects (store in associative array for quick lookup)
function getAllSubjects($conn) {
    $subjects = [];
    $sql = "SELECT id, short_name, lec_type FROM subject_info";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $subjects[$row['short_name']] = ['id' => $row['id'], 'lec_type' => $row['lec_type']];
    }
    return $subjects;
}

// Function to insert or update attendance data (execute all at once in batches)
function upsertAttendance($conn, $attendanceData) {
    $sql = "INSERT INTO total_attendance_info (student_info_id, subject_info_id, total, attend, lec_type) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE total = VALUES(total), attend = VALUES(attend)";
    $stmt = $conn->prepare($sql);

    foreach ($attendanceData as $data) {
        $stmt->bind_param('iiiss', $data['studentId'], $data['subjectId'], $data['total'], $data['attend'], $data['lec_type']);
        $stmt->execute();
    }
}

// Optimized function to increment column letters
function incrementColumnUpload($col, $step = 1) {
    // Convert column letter to index, increment, and convert back to letter
    $colIndex = Coordinate::columnIndexFromString($col);
    return Coordinate::stringFromColumnIndex($colIndex + $step);
}

if (isset($_FILES['attendance_file'])) {
    // Check if file is Excel format
    $file = $_FILES['attendance_file']['tmp_name'];
    $fileExtension = strtolower(pathinfo($_FILES['attendance_file']['name'], PATHINFO_EXTENSION));

    if ($fileExtension !== 'xlsx' && $fileExtension !== 'xls') {
        echo "<script>
            Swal.fire('Error', 'Invalid file format. Please upload an Excel file (.xls, .xlsx)', 'error')
            .then(() => {
                window.location = 'total_attendance_sheet.php';
            });
        </script>";
    } else {
        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();

            // Fetch all student and subject data once
            $students = getAllStudents($conn);
            $subjects = getAllSubjects($conn);

            $attendanceData = [];
            $row = 6;
            $subRow = 3;
            $TLheadingRow = $subRow + 1;
            $col = 'D';

            while ($worksheet->getCell('A' . $row)->getValue() != '') {
                $gr_no = $worksheet->getCell('A' . $row)->getValue();
                $studentId = $students[$gr_no] ?? null;

                if ($studentId) {
                    while ($worksheet->getCell($col . $TLheadingRow)->getValue() != '') {
                        $short_name = $worksheet->getCell($col . $subRow)->getValue();
                        $subject = $subjects[$short_name] ?? null;

                        if ($subject) {
                            $subjectId = $subject['id'];
                            $subjectLecType = $subject['lec_type'];

                            if ($subjectLecType == "TL") {
                                // Lecture
                                $total_L = $worksheet->getCell($col . $row)->getValue();
                                $attend_L = $worksheet->getCell(incrementColumnUpload($col) . $row)->getValue();
                                if (isset($total_L) && isset($attend_L)) {
                                    $attendanceData[] = [
                                        'studentId' => $studentId,
                                        'subjectId' => $subjectId,
                                        'total' => $total_L,
                                        'attend' => $attend_L,
                                        'lec_type' => 'L'
                                    ];
                                }
                                $col = incrementColumnUpload($col, 2);

                                // Tutorial
                                $total_T = $worksheet->getCell($col . $row)->getValue();
                                $attend_T = $worksheet->getCell(incrementColumnUpload($col) . $row)->getValue();
                                if (isset($total_T) && isset($attend_T)) {
                                    $attendanceData[] = [
                                        'studentId' => $studentId,
                                        'subjectId' => $subjectId,
                                        'total' => $total_T,
                                        'attend' => $attend_T,
                                        'lec_type' => 'T'
                                    ];
                                }
                                $col = incrementColumnUpload($col, 2);
                            } elseif ($subjectLecType == "T") {
                                // Tutorial only
                                $total_T = $worksheet->getCell($col . $row)->getValue();
                                $attend_T = $worksheet->getCell(incrementColumnUpload($col) . $row)->getValue();
                                if (isset($total_T) && isset($attend_T)) {
                                    $attendanceData[] = [
                                        'studentId' => $studentId,
                                        'subjectId' => $subjectId,
                                        'total' => $total_T,
                                        'attend' => $attend_T,
                                        'lec_type' => 'T'
                                    ];
                                }
                                $col = incrementColumnUpload($col, 2);
                            } elseif ($subjectLecType == "L") {
                                // Lecture only
                                $total_L = $worksheet->getCell($col . $row)->getValue();
                                $attend_L = $worksheet->getCell(incrementColumnUpload($col) . $row)->getValue();
                                if (isset($total_L) && isset($attend_L)) {
                                    $attendanceData[] = [
                                        'studentId' => $studentId,
                                        'subjectId' => $subjectId,
                                        'total' => $total_L,
                                        'attend' => $attend_L,
                                        'lec_type' => 'L'
                                    ];
                                }
                                $col = incrementColumnUpload($col, 2);
                            }
                        }
                    }
                }
                $col = 'D';  // Reset column to start from 'D' for the next row
                $row++;
            }

            if (!empty($attendanceData)) {
                upsertAttendance($conn, $attendanceData);
                // Redirect with SweetAlert on success
                echo "<script>
                    Swal.fire('Success', 'Attendance data uploaded successfully!', 'success')
                    .then(() => {
                        window.location = 'total_attendance_sheet.php';
                    });
                </script>";
            } else {
                // Redirect with SweetAlert on error
                echo "<script>
                    Swal.fire('Error', 'No attendance data to upload.', 'error')
                    .then(() => {
                        window.location = 'total_attendance_sheet.php';
                    });
                </script>";
            }
        } catch (Exception $e) {
            // Handle file reading or any PhpSpreadsheet errors
            echo "<script>
                Swal.fire('Error', 'Failed to process the Excel file. Please check the file format and try again.', 'error')
                .then(() => {
                    window.location = 'total_attendance_sheet.php';
                });
            </script>";
        }
    }
}

$conn->close();
?>


<body>
    <!-- Form 2 -->
    <form  method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="file" class="block text-gray-700 font-bold mb-2">Select Excel file to upload:</label>
                    <input type="file" name="attendance_file" id="file" required
                        class="block w-full text-gray-700 border-2 rounded py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <input type="submit" value="Upload Sheet" name="submit" 
                    class="bg-cyan-500 text-white font-bold py-2 px-10 rounded hover:bg-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition">
            </form>
</body>
</html>