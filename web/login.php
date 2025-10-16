<?php
session_start(); // Start the session
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" type="image/png" href="./assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- SweetAlert library -->
    <script>
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.innerText = "Hide";
            } else {
                passwordField.type = "password";
                toggleIcon.innerText = "Show";
            }
        }
    </script>
    <style>
        body {
            background-image: url('./assets/images/mu_background.png');
            background-position: center;
            background-size: cover;
        }
          /* Hide number input spinners */
          input[type="number"]::-webkit-inner-spin-button, 
            input[type="number"]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* For Firefox */
            input[type="number"] {
                -moz-appearance: textfield;
            }
    </style>
</head>
<body class="flex items-center justify-center h-screen">
    <img src="./assets/images/mu_logo_black.png" class="w-56 absolute top-4 right-4">
    <form action="login.php" method="POST" class="bg-white p-5 rounded-2xl shadow-2xl w-96">
    <center>
        <img src="./assets/images/ict_logo.png" class="w-72">
    </center>
    <br>
    <div class="mb-4">
        <input type="number" name="username" id="username" 
            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 no-spinner"
            placeholder="Enrollment No / EMP Code" required pattern="\d+" title="Username must be numbers only" 
            autocomplete="username">
    </div>
    <div class="mb-4 relative">
        <input type="password" name="password" id="password" 
            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500"
            placeholder="Enter your password" required autocomplete="current-password">
        <br>
        <button type="button" onclick="togglePassword()" 
            class="absolute top-2 right-3 text-gray-500 focus:outline-none">
            <span id="toggleIcon" class="text-cyan-500">Show</span>
        </button>
    </div>
    <button type="submit" 
        class="w-full bg-cyan-500 hover:bg-cyan-600 text-white py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-400">
        LOGIN
    </button>
    <br><br>
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            require_once __DIR__ . '/../api/db/db_connection.php';// Database connection

            $username = $_POST['username'];
            $password = $_POST['password'];

            // Query to check username and hashed password
            $sql = "SELECT * FROM user_login WHERE username = ? AND isactive = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = $user; // Save user data in the session

                    if ($user['role'] === 'faculty') {
                        $faculty_sql = "SELECT * FROM faculty_info WHERE faculty_id = ?";
                        $faculty_stmt = $conn->prepare($faculty_sql);
                        $faculty_stmt->bind_param("s", $username);
                        $faculty_stmt->execute();
                        $faculty_result = $faculty_stmt->get_result();

                        if ($faculty_result->num_rows > 0) {
                            $faculty_data = $faculty_result->fetch_assoc();
                            $_SESSION['role'] = "faculty";
                            $_SESSION['userdata'] = $faculty_data; // Merge faculty data into session
                            header("Location: faculty/dashboard.php");
                            exit();
                        }
                    } elseif ($user['role'] === 'student') {
                        $student_sql = "SELECT * FROM student_info WHERE user_login_id = ?";
                        $student_stmt = $conn->prepare($student_sql);
                        $student_stmt->bind_param("s", $username);
                        $student_stmt->execute();
                        $student_result = $student_stmt->get_result();

                        if ($student_result->num_rows > 0) {
                            $student_data = $student_result->fetch_assoc();
                            $_SESSION['role'] = "student";
                            $_SESSION['userdata'] = $student_data;
                            header("Location: student/dashboard.php");
                            exit();
                        }
                    }  else {
                        echo "<script>
                            Swal.fire('Error', 'Unauthorized role.', 'error');
                        </script>";
                    }
                } else {
                    echo "<script>
                        Swal.fire('Error', 'Invalid password.', 'error');
                    </script>";
                }
            } else {
                echo "<script>
                    Swal.fire('Error', 'Invalid username or inactive account.', 'error');
                </script>";
            }
            $stmt->close();
            $conn->close();
        }
        ?>
    </form>
</body>
</html>
