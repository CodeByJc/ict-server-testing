<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$userdata = $_SESSION['userdata'];
$user = $_SESSION['user'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-4">Student Dashboard</h1>
        <div class="bg-white shadow-md rounded-lg p-6">
            <img class="w-32 rounded-lg" src="https://student.marwadiuniversity.ac.in:553/handler/getImage.ashx?SID=<?php echo htmlspecialchars($userdata['gr_no']); ?>" alt="Student Image">
            <br>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($userdata['first_name']." ".$userdata['last_name']); ?></p>
            <p><strong>Enrollment Number:</strong> <?php echo htmlspecialchars($userdata['enrollment_no']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
        </div>
    </div>
</body>
</html>
