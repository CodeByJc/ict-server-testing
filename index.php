<?php
// Include the database connection
include __DIR__ . '/api/db/db_connection.php';

// Fetch the number of enrolled students using MySQLi
$query = "SELECT COUNT(*) as total_students FROM student_info";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $total_students = $row['total_students'] ?? 0;
    mysqli_stmt_close($stmt);
} else {
    $total_students = 0;
    echo "Error preparing statement: " . mysqli_error($conn);
}

$query = "SELECT video_link, title FROM landing_project_links";
$result = mysqli_query($conn, $query);

$projects = [];
while ($row = mysqli_fetch_assoc($result)) {
    $video_link = $row['video_link'];
    // Convert YouTube URLs to embed format
    if (strpos($video_link, 'youtube.com') !== false || strpos($video_link, 'youtu.be') !== false) {
        $video_link = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "https://www.youtube.com/embed/$1", $video_link);
        $video_link = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtu.be\/([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "https://www.youtube.com/embed/$1", $video_link);
    }
    $projects[] = ['video_link' => $video_link, 'title' => $row['title']];
}
?>  

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT Department</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="web/assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        html {
            scroll-behavior: smooth;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        .animate-slide-up {
            animation: slideUp 1s ease-out forwards;
        }
        .animate-fade-in {
            animation: fadeIn 1.5s ease-in forwards;
        }
        .animate-fade-in-delay {
            animation: fadeIn 1.5s ease-in 0.5s forwards;
            opacity: 0;
        }
        .nav-link {
            position: relative;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #06b6d4;
            border-radius: 9999px;
            transition: all 0.3s ease;
        }
        .video-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 270px;
        }
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-videos');
    const videoItems = document.querySelectorAll('.video-item');
    let visibleCount = 8;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (toggleBtn.textContent.includes('Show more')) {
                // Show more videos
                const hiddenItems = Array.from(videoItems).slice(visibleCount, visibleCount + 4);
                hiddenItems.forEach(item => item.classList.remove('hidden'));
                visibleCount += 4;
                toggleBtn.textContent = 'Show less...';
                if (visibleCount >= videoItems.length) {
                    toggleBtn.textContent = 'Show less...';
                }
            } else {
                // Show less videos
                const visibleItems = Array.from(videoItems).slice(8);
                visibleItems.forEach(item => item.classList.add('hidden'));
                visibleCount = 8;
                toggleBtn.textContent = 'Show more...';
            }
        });
    }
});
</script>
</head>
<body class="bg-blue-100 font-sans p-lg">
    <!-- Navbar -->
    <nav class="bg-gray-100 shadow-lg fixed w-full z-20 top-0">
        <div class="px-6">
            <div class="flex justify-between h-20">
                <div class="flex">
                    <a href="./">
                        <div class="flex-shrink-0 flex items-center">
                            <img class="h-16" src="web/assets/images/ict_logo.png" alt="ICT Logo">
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-2">
                    <a id="nav-home" href="#home" class="nav-link relative rounded-xl text-gray-700 hover:bg-gray-200 hover:scale-110 hover:rounded-xl hover:text-md px-6 py-3 text-sm font-medium transition-all">Home</a>
                    <a id="nav-about" href="#about" class="nav-link relative rounded-xl text-gray-700 hover:bg-gray-200 hover:scale-110 hover:rounded-xl hover:text-md px-6 py-3 text-sm font-medium transition-all">About</a>
                    <a id="nav-learning" href="#learning" class="nav-link relative rounded-xl text-gray-700 hover:bg-gray-200 hover:scale-110 hover:rounded-xl hover:text-md px-6 py-3 text-sm font-medium transition-all">Learning</a>
                    <a id="nav-projects" href="#projects" class="nav-link relative rounded-xl text-gray-700 hover:bg-gray-200 hover:scale-110 hover:rounded-xl hover:text-md px-6 py-3 text-sm font-medium transition-all">Projects</a>
                    <a id="nav-life" href="#life" class="nav-link relative rounded-xl text-gray-700 hover:bg-gray-200 hover:scale-110 hover:rounded-md hover:text-md px-6 py-3 text-sm font-medium transition-all">Life @ ICT</a>
                    <a id="nav-login" href="web/login.php" class="nav-link relative rounded-xl text-cyan-500 hover:bg-cyan-500 hover:text-white hover:scale-110 hover:rounded-xl hover:text-md px-6 py-3 text-sm font-medium transition-all">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-24">
        <!-- Home Section -->
        <section id="home" class="min-h-screen bg-white flex items-center justify-center">
            <div class="bg-blue-100 w-full h-full absolute rounded-br-[150px]"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 mt-20">
                <div class="flex items-center justify-center mb-4">
                    <div class="h-px w-48 bg-gradient-to-r from-transparent via-cyan-500 to-transparent"></div>
                    <h1 class="text-xl font-bold text-cyan-500 mx-4 font-[Roboto] animate-slide-up">WELCOME TO THE</h1>
                    <div class="h-px w-48 bg-gradient-to-r from-transparent via-cyan-500 to-transparent"></div>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4 animate-fade-in">Department of <br>Information and Communication Technology</h1>
                <p class="text-lg text-gray-600 animate-fade-in-delay">"Empowering the future through technology and innovation"</p>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="ml-10 min-h-screen rounded-s-[150px] bg-white flex items-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-6 md:mb-0">
                    <img src="./web/assets/images/about_img.png" alt="ICT Team" class="w-full h-auto rounded-2xl hover:shadow-2xl shadow-lg transition-all">
                </div>
                <div class="md:w-1/2 md:pl-10">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">About Us</h1>
                    <p class="text-gray-600 mb-6">
                        Information and Communication Technology is an engineering branch that came into existence with a mission to provide a wider perspective on the nature, use, and applications of technologies to the living world.
                    </p>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Courses Offered:</h3>
                    <ul class="list-disc list-inside text-gray-600 mb-6">
                        <li>Diploma in Information & Communication Technology</li>
                        <li>B.Tech in Information & Communication Technology</li>
                    </ul>
                    <br>
                    <p class="text-cyan-500 border-2 px-4 py-4 rounded-2xl text-lg font-bold hover:border-cyan-500 transition cursor-pointer">
                        <span id="student-count" class="px-3 py-1 text-white rounded-lg bg-cyan-500">0</span> Enrolled Students
                    </p>
                </div>
            </div>
        </section>

        <!-- Learning Section -->
        <div class="bg-white">
            <section id="learning" class="min-h-screen bg-blue-100 rounded-e-[150px] flex items-center justify-center py-10">
                <div class="mx-auto px-20 flex flex-col md:flex-row">
                    <div class="md:w-1/3 bg-white shadow-lg rounded-2xl p-6 mb-6 md:mb-0">
                        <h2 class="text-3xl font-bold text-cyan-600 mb-4">Project Based Learning</h2>
                        <div class="h-1 w-32 bg-cyan-500 mb-8 rounded-full"></div>
                        <ul class="space-y-4">
                            <li class="flex items-center p-3 border-2 rounded-2xl hover:rounded-full hover:border-2 hover:border-cyan-500 cursor-pointer transition-all duration-100 selected" onclick="showDetails('human-centric', this)">
                                <div class="w-10 h-10 bg-cyan-500 text-white flex items-center justify-center rounded-full mr-4">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Human Centric Projects</span>
                            </li>
                            <li class="flex items-center p-3 border-2 rounded-2xl hover:rounded-full hover:border-2 hover:border-cyan-500 cursor-pointer transition-all duration-100" onclick="showDetails('interdisciplinary', this)">
                                <div class="w-10 h-10 bg-cyan-500 text-white flex items-center justify-center rounded-full mr-4">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Interdisciplinary Projects</span>
                            </li>
                            <li class="flex items-center p-3 border-2 rounded-2xl hover:rounded-full hover:border-2 hover:border-cyan-500 cursor-pointer transition-all duration-100" onclick="showDetails('startup-mindset', this)">
                                <div class="w-10 h-10 bg-cyan-500 text-white flex items-center justify-center rounded-full mr-4">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Encourage to cultivate Startup mindsets</span>
                            </li>
                            <li class="flex items-center p-3 border-2 rounded-2xl hover:rounded-full hover:border-2 hover:border-cyan-500 cursor-pointer transition-all duration-100" onclick="showDetails('problem-solving', this)">
                                <div class="w-10 h-10 bg-cyan-500 text-white flex items-center justify-center rounded-full mr-4">
                                    <i class="fas fa-puzzle-piece"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Problem solving ability</span>
                            </li>
                        </ul>
                    </div>
                    <div class="md:w-2/3 md:pl-6">
                        <div id="learning-details" class="bg-white shadow-lg rounded-tr-3xl rounded-bl-3xl p-6 transition-opacity duration-500 opacity-100">
                            <h3 class="text-2xl font-bold text-gray-900 mb-4" id="details-title">Select an option to learn more</h3>
                            <div class="h-1 w-32 bg-cyan-500 mb-8 rounded-full"></div>
                            <p class="text-gray-600" id="details-description">Click on any learning approach from the left menu to see its details here.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Projects Section -->
        <section id="projects" class="ml-10 min-h-screen bg-white rounded-s-[150px] flex items-center justify-center py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Projects</h2>
                <p class="text-gray-600 mb-8">Discover innovative projects developed by our students and faculty.</p>
                <div id="video-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
                    <?php foreach ($projects as $index => $project): ?>
                        <div class="video-item video-card <?php echo $index >= 8 ? 'hidden' : ''; ?>">
                            <div class="relative" style="padding-top: 56.25%;">
                                <?php if (!empty($project['video_link']) && strpos($project['video_link'], 'youtube.com/embed') !== false): ?>
                                    <iframe class="absolute top-0 left-0 w-full h-full rounded-t-lg"
                                            src="<?php echo htmlspecialchars($project['video_link']); ?>"
                                            title="<?php echo htmlspecialchars($project['title']); ?>"
                                            frameborder="0"
                                            allowfullscreen
                                            loading="lazy"></iframe>
                                <?php else: ?>
                                    <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center bg-gray-200 rounded-t-lg">
                                        <p class="text-gray-500">Video unavailable</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($project['title']); ?></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($projects) > 8): ?>
                    <div class="text-center mt-8">
                        <button id="toggle-videos" class="bg-cyan-500 text-white px-6 py-2 rounded-lg hover:bg-cyan-600 transition">
                            Show more...
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Life @ ICT Section -->
        <div class="bg-white">
            <section id="life" class="min-h-screen bg-blue-100 rounded-tr-[150px] flex items-center">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Life @ ICT</h2>
                    <p class="text-gray-600">Experience the vibrant community and exciting opportunities at ICT.</p>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p>© <?php echo date("Y"); ?> ICT Department. All rights reserved.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        function animateCount(element, start, end, duration) {
            let startTime = null;
            const step = (timestamp) => {
                if (!startTime) startTime = timestamp;
                const progress = timestamp - startTime;
                const current = Math.min(Math.floor((progress / duration) * (end - start) + start), end);
                element.textContent = current;
                if (progress < duration) {
                    requestAnimationFrame(step);
                } else {
                    element.textContent = end;
                }
            };
            requestAnimationFrame(step);
        }

        const totalStudents = <?php echo json_encode($total_students); ?>;

        window.onload = function() {
            const studentCountElement = document.getElementById('student-count');
            animateCount(studentCountElement, 1, totalStudents, 2000);
        };

        function showDetails(option, element) {
            const menuItems = document.querySelectorAll('#learning .md\\:w-1\\/3 ul li');
            menuItems.forEach(item => item.classList.remove('selected'));
            if (element) {
                element.classList.add('selected');
            }

            const details = {
                'human-centric': {
                    title: 'Human Centric Projects',
                    description: 'Our human-centric projects are designed to place people at the heart of technological innovation. Students engage in hands-on experiences where they learn to identify real-world challenges faced by diverse communities. By applying principles of empathy, user research, and iterative design, they develop solutions that are not only functional but also deeply impactful. This approach fosters a mindset of user-focused innovation, preparing students to create technology that enhances lives, improves accessibility, and addresses societal needs in meaningful ways.'
                },
                'interdisciplinary': {
                    title: 'Interdisciplinary Projects',
                    description: 'Interdisciplinary projects are a cornerstone of our learning philosophy, encouraging students to break down traditional academic silos and collaborate across various fields such as engineering, design, social sciences, and business. These projects challenge students to tackle complex, multifaceted problems that require diverse perspectives and skill sets. For example, a project might involve designing a smart healthcare system by combining expertise in software development, medical research, and user experience design. Through this process, students cultivate innovation, adaptability, and the ability to think holistically, preparing them for the interconnected challenges of the modern world.'
                },
                'startup-mindset': {
                    title: 'Encourage to cultivate Startup mindsets',
                    description: 'We believe in nurturing the next generation of entrepreneurs by encouraging a startup mindset among our students. This approach involves teaching them to think like innovators and risk-takers, equipping them with the skills to identify opportunities, develop creative solutions, and transform ideas into viable products or services. Through workshops, mentorship, and real-world simulations, students learn essential entrepreneurial skills such as market analysis, prototyping, pitching ideas, and securing funding. By fostering resilience, adaptability, and a proactive attitude, we prepare them to launch their own ventures or bring an entrepreneurial spirit to any organization they join.'
                },
                'problem-solving': {
                    title: 'Problem solving ability',
                    description: 'Our curriculum places a strong emphasis on developing exceptional problem-solving abilities, a critical skill for success in the ever-evolving field of technology. Students are exposed to a wide range of real-world challenges, from optimizing algorithms to designing sustainable systems, and are encouraged to approach these problems with analytical rigor and creative thinking. Through project-based learning, they practice breaking down complex issues into manageable parts, exploring multiple solutions, and iterating based on feedback. This process not only sharpens their technical skills but also builds confidence, critical thinking, and the ability to innovate under pressure, ensuring they are well-prepared to address the technological challenges of tomorrow.'
                }
            };

            const selected = details[option];
            const titleElement = document.getElementById('details-title');
            const descriptionElement = document.getElementById('details-description');
            const detailsElement = document.getElementById('learning-details');

            detailsElement.classList.remove('opacity-100');
            detailsElement.classList.add('opacity-0');
            setTimeout(() => {
                titleElement.textContent = selected.title;
                descriptionElement.textContent = selected.description;
                detailsElement.classList.remove('opacity-0');
                detailsElement.classList.add('opacity-100');
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const firstOption = document.querySelector('#learning .md\\:w-1\\/3 ul li');
            showDetails('human-centric', firstOption);
        });

        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-link');
            let currentSection = '';

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.scrollY >= sectionTop - 50 && window.scrollY < sectionTop + sectionHeight - 50) {
                    currentSection = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${currentSection}`) {
                    link.classList.add('active');
                }
            });
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });

        window.addEventListener('scroll', updateActiveNavLink);

        document.addEventListener('DOMContentLoaded', function() {
            const firstNavLink = document.querySelector('#nav-home');
            firstNavLink.classList.add('active');
            updateActiveNavLink();
        });
    </script>
</body>
</html>