<?php
// Handle API-like request for package data
if (isset($_GET['action']) && $_GET['action'] === 'get_package_data') {
    header('Content-Type: application/json');

    // Connect to the database
    include('../../api/db/db_connection.php');

    $batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : null;

    $query = "
    SELECT 
        CASE 
            WHEN avg_package < 5 THEN '0-5 LPA'
            WHEN avg_package >= 5 AND avg_package < 10 THEN '5-10 LPA'
            WHEN avg_package >= 10 AND avg_package < 15 THEN '10-15 LPA'
            WHEN avg_package >= 15 AND avg_package < 20 THEN '15-20 LPA'
            ELSE '20+ LPA'
        END AS package_range,
        COUNT(*) AS placement_count
    FROM (
        SELECT (ps.package_start + ps.package_end) / 2 AS avg_package
        FROM placed_student_info ps
        JOIN student_info s ON ps.student_info_id = s.id
        WHERE s.batch_info_id = ? OR ? IS NULL
    ) AS subquery
    GROUP BY package_range
    ORDER BY FIELD(package_range, '0-5 LPA', '5-10 LPA', '10-15 LPA', '15-20 LPA', '20+ LPA')";

    $stmt = $conn->prepare($query);
    if ($batch_id) {
        $stmt->bind_param('ii', $batch_id, $batch_id);
    } else {
        $stmt->bind_param('is', $batch_id, $batch_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);

    $stmt->close();
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 text-gray-800 flex">
<?php include('./sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content pl-64 flex-1">
<?php
$page_title = "Dashboard";
include('./navbar.php');

// Connect to the database
include('../../api/db/db_connection.php');

// Query to get the total number of holidays
$holiday_query = "SELECT COUNT(*) AS total_holidays FROM holiday_info";
$holiday_result = $conn->query($holiday_query);
$total_holidays = 0;
if ($holiday_result && $row = $holiday_result->fetch_assoc()) {
    $total_holidays = $row['total_holidays'];
}

// Query for semester-wise student count
$student_query = "
SELECT
    smi.sem,
    smi.edu_type,
    COUNT(*) AS total
FROM student_info si
JOIN sem_info smi ON si.sem_info_id = smi.id
GROUP BY smi.edu_type, smi.sem
ORDER BY smi.edu_type, smi.sem";
$student_result = $conn->query($student_query);
$student_data = [];
if ($student_result) {
    while ($row = $student_result->fetch_assoc()) {
        $student_data[] = $row;
    }
}

// Query to get the total number of students
$student_count_query = "SELECT COUNT(*) AS total_students FROM student_info";
$student_count_result = $conn->query($student_count_query);
$total_students = 0;

if ($student_count_result && $row = $student_count_result->fetch_assoc()) {
    $total_students = (int) ($row['total_students'] ?? 0);
}


// Query to get the total number of subjects
$subject_count_query = "SELECT COUNT(*) AS total_subjects FROM subject_info";
$subject_count_result = $conn->query($subject_count_query);
$total_subjects = 0;
if ($subject_count_result && $row = $subject_count_result->fetch_assoc()) {
    $total_subjects = $row['total_subjects'];
}

// Query to get the total number of faculties
$faculty_count_query = "SELECT COUNT(*) AS total_faculties FROM faculty_info";
$faculty_count_result = $conn->query($faculty_count_query);
$total_faculties = 0;
if ($faculty_count_result && $row = $faculty_count_result->fetch_assoc()) {
    $total_faculties = $row['total_faculties'];
}

// Query to get all batches for dropdown
$batch_query = "SELECT id, CONCAT(batch_start_year, '-', batch_end_year) AS batch_name FROM batch_info ORDER BY batch_start_year";
$batch_result = $conn->query($batch_query);
$batches = [];
if ($batch_result) {
    while ($row = $batch_result->fetch_assoc()) {
        $batches[] = $row;
    }
}

// Query for students placed by average package range (initially all batches)
$package_range_query = "
SELECT 
    CASE 
        WHEN avg_package < 5 THEN '0-5 LPA'
        WHEN avg_package >= 5 AND avg_package < 10 THEN '5-10 LPA'
        WHEN avg_package >= 10 AND avg_package < 15 THEN '10-15 LPA'
        WHEN avg_package >= 15 AND avg_package < 20 THEN '15-20 LPA'
        ELSE '20+ LPA'
    END AS package_range,
    COUNT(*) AS placement_count
FROM (
    SELECT (ps.package_start + ps.package_end) / 2 AS avg_package
    FROM placed_student_info ps
    JOIN student_info s ON ps.student_info_id = s.id
) AS subquery
GROUP BY package_range
ORDER BY FIELD(package_range, '0-5 LPA', '5-10 LPA', '10-15 LPA', '15-20 LPA', '20+ LPA')";
$package_range_result = $conn->query($package_range_query);
$package_range_data = [];
if ($package_range_result) {
    while ($row = $package_range_result->fetch_assoc()) {
        $package_range_data[] = $row;
    }
}

// Query for placement percentage by batch (only batches ending on or before current year)
$placement_percentage_query = "
SELECT 
    b.id,
    CONCAT(b.batch_start_year, '-', b.batch_end_year) AS batch_name,
    COALESCE(COUNT(DISTINCT ps.id), 0) AS placed_count,
    COALESCE(COUNT(DISTINCT pse.id), 0) AS enrolled_count,
    CASE 
        WHEN COUNT(DISTINCT pse.id) > 0 
        THEN (COUNT(DISTINCT ps.id) / COUNT(DISTINCT pse.id) * 100) 
        ELSE 0 
    END AS placement_percentage
FROM batch_info b
LEFT JOIN student_info s ON s.batch_info_id = b.id
LEFT JOIN placement_support_enroll pse ON s.id = pse.student_info_id
LEFT JOIN placed_student_info ps ON s.id = ps.student_info_id
WHERE b.batch_end_year <= YEAR(CURDATE())
GROUP BY b.id, b.batch_start_year, b.batch_end_year
ORDER BY b.batch_start_year";
$placement_percentage_result = $conn->query($placement_percentage_query);
$placement_percentage_data = [];
if ($placement_percentage_result) {
    while ($row = $placement_percentage_result->fetch_assoc()) {
        $placement_percentage_data[] = $row;
    }
}
?>

<div class="p-6">
    <!-- Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- Holiday Card -->
        <div class="bg-white border-2 rounded-xl p-6 text-center transform transition-all hover:scale-105 cursor-pointer group"
             onclick="window.location.href='holiday.php';">
            <div class="flex flex-col items-center justify-center space-y-2">
                <div class="w-16 h-16 flex items-center justify-center rounded-full border-2 border-yellow-400">
                    <i class="fa-solid fa-sun text-2xl text-yellow-400"></i>
                </div>
                <div class="flex flex-row items-center justify-center space-x-2">
                    <p id="holiday-count" class="text-xl font-bold text-yellow-500">00</p>
                    <h2 class="text-yellow-400 text-xl font-semibold">Holidays</h2>
                </div>
            </div>
        </div>

        <!-- Total Students Card -->
        <div class="bg-white border-2 rounded-xl p-6 text-center transform transition-all hover:scale-105 cursor-pointer group"
             onclick="window.location.href='student_search_page.php';">
            <div class="flex flex-col items-center justify-center space-y-2">
                <div class="w-16 h-16 flex items-center justify-center rounded-full border-2 border-cyan-500">
                    <i class="fa-solid fa-users text-2xl text-cyan-500"></i>
                </div>
                <div class="flex flex-row items-center justify-center space-x-2">
                    <p id="student-count" class="text-xl font-bold text-cyan-600">
                        <?= htmlspecialchars($total_students) ?>
                    </p>
                    <h2 class="text-cyan-500 text-xl font-semibold">Students</h2>
                </div>

            </div>
        </div>

        <!-- Total Faculties Card -->
        <div class="bg-white border-2 rounded-xl p-6 text-center transform transition-all hover:scale-105 cursor-pointer group"
             onclick="window.location.href='faculty_list.php';">
            <div class="flex flex-col items-center justify-center space-y-2">
                <div class="w-16 h-16 flex items-center justify-center rounded-full border-2 border-blue-500">
                    <i class="fa-solid fa-chalkboard-user text-2xl text-blue-500"></i>
                </div>
                <div class="flex flex-row items-center justify-center space-x-2">
                    <p id="faculty-count" class="text-xl font-bold text-blue-600">00</p>
                    <h2 class="text-blue-500 text-xl font-semibold">Faculties</h2>
                </div>
            </div>
        </div>

        <!-- Total Subjects Card -->
        <div class="bg-white border-2 rounded-xl p-6 text-center transform transition-all hover:scale-105 cursor-pointer group"
             onclick="window.location.href='subjects.php';">
            <div class="flex flex-col items-center justify-center space-y-2">
                <div class="w-16 h-16 flex items-center justify-center rounded-full border-2 border-green-500">
                    <i class="fa-solid fa-book text-2xl text-green-500"></i>
                </div>
                <div class="flex flex-row items-center justify-center space-x-2">
                    <p id="subject-count" class="text-xl font-bold text-green-600">00</p>
                    <h2 class="text-green-500 text-xl font-semibold">Subjects</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Visualizations Section -->
    <div class="mt-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Placement Insights</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Bar Chart: Students Placed by Average Package -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Students Placed by Average Package</h3>
                    <select id="batchSelect" class="border border-gray-300 rounded-md p-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>"><?php echo $batch['batch_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <canvas id="packageBarChart" height="200"></canvas>
            </div>

            <!-- Line Chart: Placement Percentage by Batch -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Placement Percentage by Batch</h3>
                <canvas id="batchPlacementLineChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Count-Up Animation and Charts -->
<script>
// Count-Up Animation Function
const countUp = (elementId, start, end, duration) => {
    const element = document.getElementById(elementId);
    const range = end - start;
    const stepTime = Math.abs(Math.floor(duration / range));
    let current = start;
    const increment = end > start ? 1 : -1;

    const timer = setInterval(() => {
        current += increment;
        element.textContent = current;
        if (current === end) {
            clearInterval(timer);
        }
    }, stepTime);
};

// Animate Counts
const totalHolidays = <?php echo $total_holidays; ?>;
countUp('holiday-count', 0, totalHolidays, 1000);

const totalStudents = <?php echo (int)$total_students; ?>;
countUp('student-count', 0, totalStudents, 1000);

const totalFaculties = <?php echo $total_faculties; ?>;
countUp('faculty-count', 0, totalFaculties, 1000);

const totalSubjects = <?php echo $total_subjects; ?>;
countUp('subject-count', 0, totalSubjects, 1000);

// Bar Chart: Students Placed by Average Package
let packageChartInstance = null;
const initialPackageData = <?php echo json_encode($package_range_data); ?>;
const renderPackageChart = (data) => {
    if (packageChartInstance) {
        packageChartInstance.destroy();
    }
    packageChartInstance = new Chart(document.getElementById('packageBarChart'), {
        type: 'bar',
        data: {
            labels: data.map(item => item.package_range),
            datasets: [{
                label: 'Number of Students Placed',
                data: data.map(item => item.placement_count),
                backgroundColor: 'rgba(59, 130, 246, 0.6)', // Blue shade
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Students'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Package Range (LPA)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
};

// Initial render
renderPackageChart(initialPackageData);

// Batch selection handler
document.getElementById('batchSelect').addEventListener('change', async (event) => {
    const batchId = event.target.value;
    let url = '?action=get_package_data';
    if (batchId !== 'all') {
        url += `&batch_id=${batchId}`;
    }

    try {
        const response = await fetch(url);
        const data = await response.json();
        renderPackageChart(data);
    } catch (error) {
        console.error('Error fetching package data:', error);
    }
});

// Line Chart: Placement Percentage by Batch
const placementPercentageData = <?php echo json_encode($placement_percentage_data); ?>;
const batchPlacementLineChart = new Chart(document.getElementById('batchPlacementLineChart'), {
    type: 'line',
    data: {
        labels: placementPercentageData.map(item => item.batch_name),
        datasets: [{
            label: 'Placement Percentage',
            data: placementPercentageData.map(item => item.placement_percentage),
            borderColor: 'rgba(236, 72, 153, 1)', // Pink shade
            backgroundColor: 'rgba(236, 72, 153, 0.2)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(236, 72, 153, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(236, 72, 153, 1)'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Placement Percentage (%)'
                },
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Batch'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.raw.toFixed(2)}%`;
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>