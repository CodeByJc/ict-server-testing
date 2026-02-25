<?php
include('../../api/db/db_connection.php');
include('../../api/notifications/fcm_functions.php'); 


$leave_id = intval($_GET['leave_id']);

if (isset($_GET['leave_id'])) {
    // Fetch leave information from the database
    $leave_query = "SELECT li.*,CONCAT(si.first_name, ' ', si.last_name) AS student_name,si.enrollment_no
                    FROM leave_info li  
                    JOIN student_info si ON li.student_info_id = si.id
                    WHERE li.id = $leave_id";
    $company_result = mysqli_query($conn, $leave_query);
    $leave = mysqli_fetch_assoc($company_result);

    if (!$leave) {
        die("Leave not found.");
    }
} else {
    die("Leave ID is missing.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $leave_id = intval($_POST['leave_id']);
    $status = $_POST['status'];
    $reply = mysqli_real_escape_string($conn, $_POST['reply']);

    $update_query = "UPDATE leave_info SET leave_status = '$status', reply = '$reply' WHERE id = $leave_id";
    
    if (mysqli_query($conn, $update_query)) {
        echo "success";
        $message = ($status === 'approved') ? "Your leave is APPROVED!" : "Your leave is DECLINED!";
        sendFCMNotification($leave['enrollment_no'],$message,$reply);
    } else {
        echo "error: " . mysqli_error($conn); // Output the error for debugging
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Details</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Leave Details";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <!-- Back Button -->
            <a href="students_leave.php" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all">
                <i class="fa-solid fa-angle-left"></i> Back
            </a>
            <div class="bg-white p-6 rounded-lg drop-shadow-xl">
                <h1 class="text-xl font-bold">
                    <?php echo $leave['student_name']; 
                        $bgColor = ($leave['leave_status'] == "pending") ? "bg-yellow-500" : 
                                (($leave['leave_status'] == "approved") ? "bg-green-500" : "bg-red-500");
                    ?>
                    <span class="ml-5 p-1 px-2 <?php echo $bgColor; ?> w-auto font-bold text-sm rounded-md text-white">
                        <?php echo strtoupper($leave['leave_status']); ?>
                    </span>
                </h1>

                <div class="rounded-full w-full h-1 mt-2 bg-slate-100"></div>

                <!-- Grid Layout -->
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <!-- Date & Time -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-slate-600 rounded mr-2"></div>
                            <span>Date & Time</span>
                        </h2>
                        <div class="pl-5 text-cyan-600">
                            <?php
                                echo date("d/m/Y", strtotime($leave['created_at']));
                                echo " - ";
                                echo date("g:i A", strtotime($leave['created_at']));
                            ?>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div>
                        <h2 class="text-xl mb-2 font-semibold flex items-center">
                            <div class="pl-1.5 py-3 bg-slate-600 rounded mr-2"></div>
                            <span>Attachments</span>
                        </h2>
                        <div class="pl-5 text-cyan-600">
                            <?php if (!empty($leave['document_proof'])): ?>
                                <a href="<?php echo $leave['document_proof']; ?>" target="_blank">
                                    <button class="bg-cyan-600 text-white px-4 py-1 rounded-md hover:bg-cyan-700 transition-all">
                                        View Document
                                    </button>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-500">No Document Available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
                    <div class="pl-1.5 py-3 bg-slate-600 rounded mr-3"></div>
                    <span>Reason</span>
                </h2>
                <div class="pl-5 text-cyan-600">
                    <?php echo nl2br(htmlspecialchars($leave['reason'])); ?>
                </div>

                <!-- Status Buttons -->
                <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
                    <div class="pl-1.5 py-3 bg-slate-600 rounded mr-3"></div>
                    <span>Update Status</span>
                </h2>
                <div class="pl-5 flex space-x-4 mt-3">
                    <button id="approveBtn" class="status-btn px-4 py-1 rounded-full text-white bg-gray-500 hover:bg-green-600 transition-all"
                        data-status="approved">Approve</button>
                    <button id="declineBtn" class="status-btn px-4 py-1 rounded-full text-white bg-gray-500 hover:bg-red-600 transition-all"
                        data-status="declined">Decline</button>
                </div>

                <!-- Reply Textbox -->
                <h2 class="text-xl mt-5 mb-2 font-semibold flex items-center">
                    <div class="pl-1.5 py-3 bg-slate-600 rounded mr-3"></div>
                    <span>Reply</span>
                </h2>
                <div class="pl-5">
                    <textarea id="replyText" class="w-full p-2 border border-gray-300 rounded-md" rows="3"><?php echo htmlspecialchars($leave['reply']); ?></textarea>
                </div>

                <!-- Update Button -->
                <div class="pl-5 mt-4">
                    <button id="updateBtn" class="px-7 py-2 bg-cyan-500 text-white rounded-xl hover:bg-cyan-500 hover:px-10 transition-all">
                        Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedStatus = "<?php echo $leave['leave_status']; ?>"; // Keep track of the selected status

        document.querySelectorAll(".status-btn").forEach(button => {
            button.addEventListener("click", function () {
                // Reset all buttons to gray
                document.querySelectorAll(".status-btn").forEach(btn => {
                    btn.classList.remove("bg-green-600", "bg-red-600");
                    btn.classList.add("bg-gray-500");
                }); 

                // Apply selected color
                if (this.dataset.status === "approved") {
                    this.classList.remove("bg-gray-500");
                    this.classList.add("bg-green-600");
                } else {
                    this.classList.remove("bg-gray-500");
                    this.classList.add("bg-red-600");
                }
                selectedStatus = this.dataset.status;
            });
        });

        document.getElementById("updateBtn").addEventListener("click", function () {
            let reply = document.getElementById("replyText").value;
            let leaveId = "<?php echo $leave_id; ?>"; // Pass the leave ID to the AJAX request
            Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait while the meeting is being saved.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
            $.ajax({
                url: "<?php echo $_SERVER['PHP_SELF']; ?>?leave_id=" + leaveId,
                type: "POST",
                data: { leave_id: leaveId, status: selectedStatus, reply: reply },
                success: function (response) {
                    console.log(response); // Debugging: Check server response
                    if (response.trim() == "success") {
                        Swal.fire({
                            title: "Success!",
                            text: "Leave status updated successfully.",
                            icon: "success",
                            timer: 1000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = "students_leave.php";
                        });
                    } else {
                        Swal.fire("Error!", "Failed to update leave status ", "error");
                    }
                },
                error: function () {
                    Swal.fire("Error!", "Something went wrong. Try again.", "error");
                }
            });
        });
    </script>
</body>
</html>