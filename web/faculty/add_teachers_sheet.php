<?php
require '../../api/db/db_connection.php';
// require 'db_connection.php';
require './web/ vendor/autoload.php'; // Include PHPExcel or PHPSpreadsheet library

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if a file was uploaded and the form was submitted
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Ensure the file is an Excel file
    if (in_array($fileExtension, ['xlsx', 'xls'])) {
        // Load the Excel file
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        foreach ($sheetData as $rowIndex => $row) {
            // Skip the header row
            if ($rowIndex == 1) {
                continue;
            }

            // Check if column A is empty and break the loop if it is
            if (empty($row['A'])) {
                break; // Stop processing if column A is empty
            }

            // Extract data from columns (adjust column names based on your Excel structure)
            $facultyId = $row['A'];
            $firstName = $row['B'];
            $lastName = $row['C'];
            $gender = strtolower($row['D']);
            $facultyEmail = $row['E'];
            $facultyMob = $row['F'];
            $designation = strtolower($row['G']);
            

            // 1. Insert Faculty Data into user_login
            $facultyUsername = $facultyId;
            $facultyPassword = password_hash("$firstName@$facultyId", PASSWORD_DEFAULT);
            $role = 'faculty';

            // Check if the student user already exists
            $stmt = $conn->prepare("SELECT username FROM user_login WHERE username = ?");
            $stmt->bind_param("s", $studentUsername);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO user_login (username, password, role, isactive, email, phone_no) VALUES (?, ?, ?, 1, ?, ?)");
                $stmt->bind_param("sssss", $facultyUsername, $facultyPassword, $role, $facultyEmail, $facultyMob);
                $stmt->execute();
            }

            // 2. Insert data into student_info
            $stmt = $conn->prepare("INSERT INTO faculty_info (first_name, last_name, faculty_id, user_login_id, gender, designation) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiss", $firstName, $lastName, $facultyId, $facultyUsername, $gender,$designation);
            $stmt->execute();
        }

        echo "Faculty Data insertion completed successfully!";
    } else {
        echo "Error: Only Excel files (.xlsx, .xls) are allowed.";
    }
} else {
    echo "Error: No file uploaded or an error occurred during upload.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel File</title>
</head>
<body>
    <form action="AddNewFaculty.php" method="post" enctype="multipart/form-data">
        <label for="file">Choose Excel file:</label>
        <input type="file" name="file" id="file" accept=".xlsx, .xls" required>
        <button type="submit">Upload and Process</button>
    </form>
</body>
</html>
