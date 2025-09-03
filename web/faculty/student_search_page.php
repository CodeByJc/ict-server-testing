<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Search</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">

    <?php include('./sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Student Search";
        include('./navbar.php');
        ?>

        <!-- Search Section -->
        <div class="container p-6">
            <!-- Search Bar and Button -->
            <form class=" space-x-4 mb-6" onsubmit="event.preventDefault(); searchStudentInfo();">
                <input type="text" id="enrollment_no" class="w-80 p-2 pl-4 border border-gray-300 drop-shadow-md rounded-xl text-gray-800"
                    placeholder="Enter Enrollment No." maxlength="12" oninput="validateInput(event)" required />
                    <button id="search_btn" class="bg-cyan-500 text-white px-6 py-2 hover:px-10 transition-all rounded-full shadow-lg hover:bg-cyan-600 focus:outline-none" type="submit">
                    Search
                </button>
            </form>


            <!-- Student Information and Attendance Result -->
            <div id="student_info" class="hidden space-y-6">
                <!-- Student Info Card -->
                <div class="bg-white shadow-lg rounded-lg p-8 mb-6 border border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Student Details</h2>
                    <div id="student_details" class="space-y-4">
                        <!-- Student Details Content -->
                    </div>
                </div>

                <!-- Attendance Card -->
                <div class="bg-white shadow-lg rounded-lg p-8 mb-6 border border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Total Attendance</h2>
                    <div id="attendance_details" class="overflow-y-auto">
                        <!-- Attendance table will go here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to validate the input (enrollment number: only numbers, max length 12)
        function validateInput(event) {
            const input = event.target;
            input.value = input.value.replace(/[^0-9]/g, ''); // Only allow numbers
        }

        // Function to perform student search and display results
        function searchStudentInfo() {
            const enrollment_no = document.getElementById('enrollment_no').value;

            // Check if the enrollment number is empty or invalid
            if (!enrollment_no || enrollment_no.length < 11) {
                Swal.fire('Error', 'Please enter a valid enrollment number.', 'error');
                return;
            }

            // Show loading animation (optional)
            document.getElementById('student_info').classList.add('hidden');

            // Fetch student data with AJAX (using PHP)
            const url = `search_student.php?enrollment_no=${enrollment_no}`; // Use a separate PHP file for handling AJAX request
            fetch(url)
                .then(response => response.json()) // Expecting JSON response
                .then(data => {
                    // Check if student data is available
                    if (data.student_info.length === 0) {
                        document.getElementById('student_details').innerHTML = '<p class="text-red-500">No student found with this enrollment number.</p>';
                    } else {
                        const student = data.student_info[0];
                        document.getElementById('student_details').innerHTML = `
            <div class="space-y-2">
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Name</strong>
                    <span class="flex-1">:  ${student.student_name}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Enrollment No</strong>
                    <span class="flex-1">:  ${student.enrollment_no}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">GR No</strong>
                    <span class="flex-1">:  ${student.gr_no}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Email</strong>
                    <span class="flex-1">:  ${student.student_email}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Phone</strong>
                    <span class="flex-1">:  ${student.student_phone}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Sem/Class/Batch</strong>
                    <span class="flex-1">:  ${student.sem} - ${student.classname} ${student.classbatch}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Course Type</strong>
                    <span class="flex-1">:  ${student['upper(smi.edu_type)']}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Parent's Name</strong>
                    <span class="flex-1">:  ${student.parent_name}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Parent's Profession</strong>
                    <span class="flex-1">:  ${student.profession}</span>
                </div>
                <div class="flex">
                    <strong class="text-cyan-500 w-48">Parent's Phone</strong>
                    <span class="flex-1">:  ${student.parent_phone}</span>
                </div>
            </div>
        `;
                    }

                    const attendance = data.attendance_info;

let totalLecL = 0,
    totalAttendedL = 0,
    totalExtraL = 0;
let totalLecT = 0,
    totalAttendedT = 0,
    totalExtraT = 0;

// Group data by subject
const groupedAttendance = attendance.reduce((acc, item) => {
    if (!acc[item.short_name]) {
        acc[item.short_name] = [];
    }
    acc[item.short_name].push(item);
    return acc;
}, {});

let attendanceHtml = ` 
    <table class="w-full table-auto border-collapse border border-gray-300 text-center">
        <thead>
            <tr class="text-left bg-gray-900 text-white">
                <th class="border-b border-r border-gray-300 py-2 text-center">Subject Name</th>
                <th class="border-b border-r border-gray-300 py-2 text-center">Lecture Type</th>
                <th class="border-b border-r border-gray-300 py-2 text-center">Total Lecture</th>
                <th class="border-b border-r border-gray-300 py-2 text-center">Attended Lecture</th>
                <th class="border-b border-r border-gray-300 py-2 text-center">Extra Lecture</th>
                <th class="border-b border-r border-gray-300 py-2 text-center">Percent</th>
            </tr>
        </thead>
        <tbody>
`;

for (const [subject, records] of Object.entries(groupedAttendance)) {
    let rowSpan = records.length;
    let isFirstRow = true;

    records.forEach((item, index) => {
        const totalLectures = item.total_lec;
        const attendedLectures = item.attend_lec;
        const extraLectures = item.extra_lec;

        const percentage = ((attendedLectures + extraLectures) / totalLectures) * 100;
        const formattedPercentage = totalLectures == 0 ? 0 : percentage.toFixed(2);

        if (item.lec_type === "L") {
            totalLecL += totalLectures;
            totalAttendedL += attendedLectures;
            totalExtraL += extraLectures;
        } else if (item.lec_type === "T") {
            totalLecT += totalLectures;
            totalAttendedT += attendedLectures;
            totalExtraT += extraLectures;
        }

        attendanceHtml += ` 
            <tr class="hover:bg-gray-50 ${index === 0 ? 'border-t-2 border-gray-300' : ''}">
                ${isFirstRow ? `<td class="border-b border-r border-gray-300 px-4 py-2" title="${item.subject_name}" rowspan="${rowSpan}">${item.short_name}</td>` : ""}
                <td class="border-b border-r border-gray-300 px-4 py-2">${item.lec_type}</td>
                <td class="border-b border-r border-gray-300 px-4 py-2">${totalLectures}</td>
                <td class="border-b border-r border-gray-300 px-4 py-2">${attendedLectures}</td>
                <td class="border-b border-r border-gray-300 px-4 py-2">${extraLectures}</td>
                <td class="border-b border-r border-gray-300 px-4 py-2">${formattedPercentage}%</td>
            </tr>
        `;

        isFirstRow = false;
    });
}

function calculatePercentage(totalLec, attended, extra) {
    if (totalLec === 0) return "0%";
    const percentage = ((attended + extra) / totalLec) * 100;
    return percentage.toFixed(2) + "%";
}

attendanceHtml += `
    <tr class="bg-gray-100">
        <td colspan="2" class="border-b border-r border-gray-300 text-center py-2">Total L</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalLecL}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalAttendedL}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalExtraL}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${calculatePercentage(totalLecL, totalAttendedL, totalExtraL)}</td>
    </tr>
    <tr class="bg-gray-100">
        <td colspan="2" class="border-b border-r border-gray-300 text-center py-2">Total T</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalLecT}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalAttendedT}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalExtraT}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${calculatePercentage(totalLecT, totalAttendedT, totalExtraT)}</td>
    </tr>
`;

const totalLec = totalLecL + totalLecT;
const totalAttended = totalAttendedL + totalAttendedT;
const totalExtra = totalExtraL + totalExtraT;

attendanceHtml += `
    <tr class="bg-gray-200">
        <td colspan="2" class="border-b border-r border-gray-300 text-center py-2">Final Total</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalLec}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalAttended}</td>
        <td class="border-b border-r border-gray-300 text-center py-2">${totalExtra}</td>
        <td class="border-b border-r border-gray-300 bg-cyan-500 text-white text-xl text-center py-2">${calculatePercentage(totalLec, totalAttended, totalExtra)}</td>
    </tr>
`;

attendanceHtml += `</tbody></table>`;

document.getElementById('attendance_details').innerHTML = attendanceHtml;


                    // Show the student info and attendance sections
                    document.getElementById('student_info').classList.remove('hidden');
                })
                .catch(error => {
                    Swal.fire('Error', 'There was an error fetching student data.', 'error');
                    console.error(error);
                });
        }
    </script>
</body>

</html>