<?php
include('../../api/db/db_connection.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Manage</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        .tab-active {
            background-color: #3b82f6;
            color: white;
        }

        .tile {
            background-color: #e5e7eb;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .saved-tag {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            color: white;
        }

        .saved {
            background-color: #22c55e;
        }

        .not-saved {
            background-color: #eab308;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
    <?php include('./sidebar.php'); ?>
    <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
        <?php
        $page_title = "Timetable Manage";
        include('./navbar.php');
        ?>
        <div class="container mx-auto p-6">
            <form id="timetableForm" class="bg-white p-6 rounded-xl shadow-md">
                <!-- Semester & Program, Class and Batch in a Row -->
                <div class="flex flex-wrap -mx-3 mb-4">
                    <div class="w-full md:w-1/2 px-3 mb-4">
                        <label for="semester" class="block text-gray-700 font-bold mb-2">Semester & Program</label>
                        <select id="semester" name="semester" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                            <option value="" disabled selected>Select Semester & Program</option>
                            <?php
                            $sem_query = "SELECT id, sem, edu_type FROM sem_info ORDER BY edu_type, sem";
                            $sem_result = mysqli_query($conn, $sem_query);
                            while ($row = mysqli_fetch_assoc($sem_result)) {
                                echo "<option value='{$row['id']}'>SEM {$row['sem']} - " . strtoupper($row['edu_type']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/2 px-3 mb-4">
                        <label for="class_batch" class="block text-gray-700 font-bold mb-2">Class & Batch</label>
                        <select id="class_batch" name="class_batch" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" disabled>
                            <option value="" disabled selected>Select Class & Batch</option>
                        </select>
                    </div>
                </div>

                <!-- Tab Bar for Days (Hidden by default) -->
                <div id="day_tabs" class="mb-4 hidden">
                    <div class="flex space-x-2 border-b">
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                        foreach ($days as $index => $day) {
                            $active = $index === 0 ? 'tab-active' : '';
                            echo "<button type='button' class='tab-button px-4 py-2 font-bold text-sm capitalize rounded-full $active hover:bg-cyan-600 hover:text-white transition-all' data-day='$day'>$day</button>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Timetable Slots -->
                <div id="timetable_slots" class="mb-4">
                    <?php foreach ($days as $day) { ?>
                        <div id="slots_<?php echo $day; ?>" class="day-slots hidden">
                            <h3 class="text-lg font-semibold capitalize mb-2"><?php echo $day; ?> Slots</h3>
                            <div id="tiles_<?php echo $day; ?>" class="tiles"></div>
                            <button type="button" class="add-slot bg-cyan-500 text-white px-3 p-1 rounded-full hover:scale-110 hover:bg-cyan-600 font-bold transition-all mt-3 mb-2" data-day="<?php echo $day; ?>" disabled>+ Add Slot</button>
                        </div>
                    <?php } ?>
                </div>

                <!-- Slot Form (Hidden, shown via JS) -->
                <div id="slot_form" class="hidden bg-gray-100 p-4 rounded-xl mb-4">
                    <h3 class="text-lg font-semibold mb-2">Add/Edit Slot</h3>
                    <input type="hidden" id="slot_day" name="slot_day">
                    <input type="hidden" id="edit_index" name="edit_index">
                    <input type="hidden" id="slot_id" name="slot_id">
                    <div class="flex flex-wrap -mx-3">
                        <!-- Start and End Time -->
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="start_time" class="block text-gray-700 font-bold mb-2">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        </div>
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="end_time" class="block text-gray-700 font-bold mb-2">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        </div>
                        <!-- Subject -->
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="subject" class="block text-gray-700 font-bold mb-2">Subject</label>
                            <select id="subject" name="subject" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" disabled>
                                <option value="" disabled selected>Select Subject</option>
                            </select>
                        </div>
                        <!-- Faculty -->
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="faculty" class="block text-gray-700 font-bold mb-2">Faculty</label>
                            <select id="faculty" name="faculty" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none" disabled>
                                <option value="" disabled selected>Select Faculty</option>
                            </select>
                        </div>
                        <!-- Class Location -->
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="class_location" class="block text-gray-700 font-bold mb-2">Class Location</label>
                            <select id="class_location" name="class_location" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                                <option value="" disabled selected>Select Class Location</option>
                                <?php
                                $loc_query = "SELECT id, classname FROM class_location_info";
                                $loc_result = mysqli_query($conn, $loc_query);
                                while ($row_loc = mysqli_fetch_assoc($loc_result)) {
                                    echo "<option value='{$row_loc['id']}'>{$row_loc['classname']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Lecture Type -->
                        <div class="w-full md:w-1/2 px-3 mb-4">
                            <label for="lec_type" class="block text-gray-700 font-bold mb-2">Lecture Type</label>
                            <select id="lec_type" name="lec_type" class="w-full p-3 border-2 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                                <option value="" disabled selected>Select Lecture Type</option>
                                <option value="T">Theory</option>
                                <option value="L">Lab</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end space-x-2">
                        <button type="button" id="cancel_slot" class="bg-gray-700 text-white px-5 p-3 rounded-full hover:px-7 font-bold transition-all">Cancel</button>
                        <button type="button" id="save_slot" class="bg-cyan-600 text-white px-5 p-3 rounded-full hover:px-7 font-bold hover:bg-cyan-700 transition-all">Save Slot</button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="button" id="submit_timetable" class="bg-cyan-600 text-white px-5 p-3 rounded-full hover:px-7 font-bold hover:bg-cyan-700 transition-all" disabled>Submit Timetable</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let slots = {
                monday: [],
                tuesday: [],
                wednesday: [],
                thursday: [],
                friday: []
            };
            let savedSlots = {
                monday: [],
                tuesday: [],
                wednesday: [],
                thursday: [],
                friday: []
            };
            let selectedSemId = '';
            let selectedClassId = '';

            // Tab Switching
            $('.tab-button').click(function() {
                $('.tab-button').removeClass('tab-active');
                $(this).addClass('tab-active');
                $('.day-slots').addClass('hidden');
                $('#slots_' + $(this).data('day')).removeClass('hidden');
            });
            $('#slots_monday').removeClass('hidden'); // Show Monday by default when tabs are visible

            // Load Classes and Batches based on Semester
            $('#semester').change(function() {
                selectedSemId = $(this).val();
                $('#class_batch').prop('disabled', false).html('<option value="" disabled selected>Select Class & Batch</option>');
                $('#day_tabs').addClass('hidden');
                slots = {
                    monday: [],
                    tuesday: [],
                    wednesday: [],
                    thursday: [],
                    friday: []
                };
                savedSlots = {
                    monday: [],
                    tuesday: [],
                    wednesday: [],
                    thursday: [],
                    friday: []
                };
                $('.tiles').empty();
                $('.add-slot').prop('disabled', true);
                $('#submit_timetable').prop('disabled', true);
                if (selectedSemId) {
                    $.ajax({
                        url: 'fetch_classes.php',
                        method: 'POST',
                        data: {
                            sem_id: selectedSemId
                        },
                        success: function(data) {
                            console.log('fetch_classes raw response:', data);
                            let classes = data;
                            if (!Array.isArray(classes)) {
                                console.error('fetch_classes error: Expected an array, got:', classes);
                                if (classes && typeof classes === 'object' && classes.error) {
                                    Swal.fire('Error', classes.error, 'error');
                                } else {
                                    Swal.fire('Error', 'Invalid response format from server when loading classes.', 'error');
                                }
                                $('#class_batch').prop('disabled', true);
                                return;
                            }

                            console.log('Classes:', classes);

                            if (classes.length === 0) {
                                Swal.fire('Warning', 'No classes found for this semester', 'warning');
                                $('#class_batch').prop('disabled', true);
                                return;
                            }

                            classes.forEach(cls => {
                                let electiveSubId = cls.elective_subject_id !== null && cls.elective_subject_id !== '' ? cls.elective_subject_id : '';
                                console.log('Adding class:', cls.classname, 'electiveSubId:', electiveSubId);
                                $('#class_batch').append(
                                    `<option value="${cls.id}" data-classname="${cls.classname}" data-batch="${cls.batch}" data-group="${cls.group}" data-elective-sub-id="${electiveSubId}">
                                        ${cls.classname} - ${(cls.batch || 'No Batch').toUpperCase()}${cls.group === 'elective' ? ' (Elective)' : ''}
                                    </option>`
                                );
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('fetch_classes AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load classes. Please check your network or try again.', 'error');
                            $('#class_batch').prop('disabled', true);
                        }
                    });
                } else {
                    $('#class_batch').prop('disabled', true);
                }
            });

            // Show Day Tabs and Load Timetable when Class is Selected
            $('#class_batch').change(function() {
                selectedClassId = $(this).val();
                $('#day_tabs').removeClass('hidden');
                $('.add-slot').prop('disabled', !selectedClassId);
                $('#submit_timetable').prop('disabled', !selectedClassId);
                $('#slot_form').addClass('hidden'); // Hide slot form on class change
                if (selectedSemId && selectedClassId) {
                    loadTimetable(selectedSemId, selectedClassId);
                }
            });

            // Function to configure Lecture Type dropdown based on lec_type
            function configureLectureType(lecType, isEditing = false, preSelectedLecType = null) {
                const $lecTypeDropdown = $('#lec_type');
                $lecTypeDropdown.html(`
                    <option value="" disabled selected>Select Lecture Type</option>
                    <option value="T">Theory</option>
                    <option value="L">Lab</option>
                `);

                if (isEditing && preSelectedLecType) {
                    $lecTypeDropdown.val(preSelectedLecType);
                    if (lecType === 'L' || lecType === 'T') {
                        $lecTypeDropdown.prop('disabled', true);
                    } else {
                        $lecTypeDropdown.prop('disabled', false);
                    }
                } else {
                    if (lecType === 'LT') {
                        $lecTypeDropdown.val('');
                        $lecTypeDropdown.prop('disabled', false);
                    } else if (lecType === 'L') {
                        $lecTypeDropdown.val('L');
                        $lecTypeDropdown.prop('disabled', true);
                    } else if (lecType === 'T') {
                        $lecTypeDropdown.val('T');
                        $lecTypeDropdown.prop('disabled', true);
                    } else {
                        $lecTypeDropdown.val('');
                        $lecTypeDropdown.prop('disabled', false);
                        console.warn('Invalid lec_type:', lecType);
                    }
                }
            }

            // Load Subjects based on Class Group
            function loadSubjects(isEditing = false, preSelectedSubjectId = null) {
                if (!selectedClassId) {
                    Swal.fire('Error', 'Please select a class before adding a slot.', 'error');
                    $('#slot_form').addClass('hidden');
                    return;
                }

                let classGroup = $('#class_batch option:selected').data('group');
                let electiveSubId = $('#class_batch option:selected').data('electiveSubId') || '';
                let subjectType = classGroup === 'regular' ? 'mandatory' : 'elective';

                $('#subject').html('<option value="" disabled selected>Select Subject</option>').prop('disabled', false);

                $.ajax({
                    url: 'fetch_subjects.php',
                    method: 'POST',
                    data: {
                        sem_id: selectedSemId,
                        type: subjectType,
                        subId: electiveSubId || null
                    },
                    success: function(data) {
                        console.log('fetch_subjects raw response:', data);
                        let subjects = data;

                        if (!Array.isArray(subjects)) {
                            console.error('fetch_subjects error: Expected an array, got:', subjects);
                            if (subjects && typeof subjects === 'object' && subjects.error) {
                                Swal.fire('Error', subjects.error, 'error');
                            } else {
                                Swal.fire('Error', 'Invalid response format from server when loading subjects.', 'error');
                            }
                            $('#subject').prop('disabled', true);
                            $('#faculty').prop('disabled', true);
                            $('#lec_type').prop('disabled', true);
                            return;
                        }

                        console.log('Subjects:', subjects);

                        if (subjects.length === 0) {
                            console.error('No subjects found for sem_id:', selectedSemId, 'type:', subjectType, 'subId:', electiveSubId);
                            Swal.fire('Error', 'No subjects found for this selection. Please ensure subjects are configured.', 'error');
                            $('#subject').prop('disabled', true);
                            $('#faculty').prop('disabled', true);
                            $('#lec_type').prop('disabled', true);
                            return;
                        }

                        subjects.forEach(sub => {
                            $('#subject').append(`<option value="${sub.id}" data-lec-type="${sub.lec_type}">${sub.short_name} - ${sub.subject_name}</option>`);
                        });

                        if (classGroup === 'elective' && electiveSubId) {
                            if (subjects[0]?.id) {
                                $('#subject').val(subjects[0].id).prop('disabled', true);
                                console.log('Selected elective subject:', subjects[0].id);
                                loadFaculty(subjects[0].id, selectedClassId);
                                configureLectureType(subjects[0].lec_type, isEditing, isEditing ? $('#lec_type').val() : null);
                            } else {
                                console.error('Elective subject not found for subId:', electiveSubId);
                                Swal.fire('Error', 'Elective subject not found for this class. Please check subject data.', 'error');
                                $('#subject').prop('disabled', true);
                                $('#faculty').prop('disabled', true);
                                $('#lec_type').prop('disabled', true);
                            }
                        } else if (isEditing && preSelectedSubjectId) {
                            $('#subject').val(preSelectedSubjectId);
                            console.log('Editing mode, selected subject:', preSelectedSubjectId);
                            loadFaculty(preSelectedSubjectId, selectedClassId);
                            let selectedSubject = subjects.find(sub => sub.id == preSelectedSubjectId);
                            if (selectedSubject) {
                                configureLectureType(selectedSubject.lec_type, true, $('#lec_type').val());
                            } else {
                                console.warn('Selected subject not found in subjects list:', preSelectedSubjectId);
                                configureLectureType('LT');
                            }
                        } else {
                            $('#subject').prop('disabled', false);
                            $('#faculty').prop('disabled', true);
                            $('#lec_type').val('').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('fetch_subjects AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to load subjects. Please check your network or try again.', 'error');
                        $('#subject').prop('disabled', true);
                        $('#faculty').prop('disabled', true);
                        $('#lec_type').prop('disabled', true);
                    }
                });
            }

            // Load Faculty based on Subject
            function loadFaculty(subjectId, classId, selectedFacultyId = null) {
                console.log('loadFaculty called with:', { subjectId, classId, selectedFacultyId });
                $('#faculty').html('<option value="" disabled selected>Select Faculty</option>').prop('disabled', false);
                if (subjectId && classId) {
                    $.ajax({
                        url: 'fetch_faculty.php',
                        method: 'POST',
                        data: {
                            subject_id: subjectId,
                            class_id: classId
                        },
                        success: function(data) {
                            console.log('fetch_faculty raw response:', data);
                            console.log('fetch_faculty response type:', typeof data, 'isArray:', Array.isArray(data));
                            let faculty = data;

                            if (!Array.isArray(faculty)) {
                                console.error('fetch_faculty error: Expected an array, got:', faculty);
                                if (faculty && typeof faculty === 'object' && faculty.error) {
                                    Swal.fire('Error', faculty.error, 'error');
                                } else {
                                    Swal.fire('Error', 'Invalid response format from server when loading faculty. Check console for details.', 'error');
                                }
                                $('#faculty').prop('disabled', true);
                                return;
                            }

                            console.log('Faculty:', faculty);

                            if (faculty.length === 0) {
                                console.warn('No faculty found for subject_id:', subjectId, 'class_id:', classId);
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'No Faculty Assigned',
                                    text: 'No faculty are assigned to this subject and class. Please assign faculty in the admin panel.',
                                    confirmButtonText: 'OK'
                                });
                                $('#faculty').prop('disabled', true);
                                return;
                            }

                            faculty.forEach(fac => {
                                $('#faculty').append(`<option value="${fac.id}">${fac.first_name} ${fac.last_name}</option>`);
                            });

                            if (faculty.length === 1 && !selectedFacultyId) {
                                $('#faculty').val(faculty[0].id);
                                console.log('Auto-selected single faculty:', faculty[0].id);
                            } else if (selectedFacultyId) {
                                $('#faculty').val(selectedFacultyId);
                                console.log('Selected faculty:', selectedFacultyId);
                            }

                            $('#faculty').prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            console.error('fetch_faculty AJAX error:', status, error, 'Response:', xhr.responseText);
                            Swal.fire('Error', 'Failed to load faculty. Please check your network or try again.', 'error');
                            $('#faculty').prop('disabled', true);
                        }
                    });
                } else {
                    console.error('loadFaculty: Invalid subjectId or classId', { subjectId, classId });
                    Swal.fire('Error', 'Invalid subject or class selected for faculty loading.', 'error');
                    $('#faculty').prop('disabled', true);
                }
            }

            // Load Existing Timetable
            function loadTimetable(semId, classId) {
                $.ajax({
                    url: 'fetch_timetable.php',
                    method: 'POST',
                    data: {
                        sem_id: semId,
                        class_id: classId
                    },
                    success: function(data) {
                        console.log('fetch_timetable raw response:', data);
                        console.log('fetch_timetable response type:', typeof data, 'isArray:', Array.isArray(data));
                        let timetable = data;

                        if (!Array.isArray(timetable)) {
                            console.error('fetch_timetable error: Expected an array, got:', timetable);
                            if (timetable && typeof timetable === 'object' && timetable.error) {
                                Swal.fire('Error', timetable.error, 'error');
                            } else {
                                Swal.fire('Error', 'Invalid response format from server when loading timetable. Check console for details.', 'error');
                            }
                            return;
                        }

                        console.log('Timetable:', timetable);

                        slots = {
                            monday: [],
                            tuesday: [],
                            wednesday: [],
                            thursday: [],
                            friday: []
                        };
                        savedSlots = {
                            monday: [],
                            tuesday: [],
                            wednesday: [],
                            thursday: [],
                            friday: []
                        };
                        timetable.forEach(slot => {
                            savedSlots[slot.day].push({
                                id: slot.id,
                                start_time: slot.start_time,
                                end_time: slot.end_time,
                                class_id: slot.class_info_id,
                                subject_id: slot.subject_info_id,
                                faculty_id: slot.faculty_info_id,
                                location_id: slot.class_location_info_id,
                                lec_type: slot.lec_type,
                                classname: slot.classname,
                                batch: slot.batch,
                                subject_name: slot.subject_name,
                                faculty_name: slot.faculty_name,
                                saved: true
                            });
                        });
                        Object.keys(slots).forEach(day => updateTiles(day));
                    },
                    error: function(xhr, status, error) {
                        console.error('fetch_timetable AJAX error:', status, error, 'Response:', xhr.responseText);
                        Swal.fire('Error', 'Failed to load timetable. Please check your network or try again.', 'error');
                    }
                });
            }

            // Add Slot
            $('.add-slot').click(function() {
                if (!selectedClassId) {
                    Swal.fire('Error', 'Please select a class before adding a slot.', 'error');
                    return;
                }
                $('#slot_day').val($(this).data('day'));
                $('#edit_index').val('');
                $('#slot_id').val('');
                $('#slot_form').removeClass('hidden');
                $('#start_time, #end_time, #subject, #faculty, #class_location, #lec_type').val('');
                $('#subject, #faculty').prop('disabled', true);
                $('#lec_type').val('').prop('disabled', false);
                loadSubjects();
                $('#save_slot').text('Save Slot');
            });

            // Cancel Slot
            $('#cancel_slot').click(function() {
                $('#slot_form').addClass('hidden');
            });

            // Save Slot
            $('#save_slot').click(function() {
                let day = $('#slot_day').val();
                let startTime = $('#start_time').val();
                let endTime = $('#end_time').val();
                let classId = selectedClassId;
                let subjectId = $('#subject').val();
                let facultyId = $('#faculty').val();
                let locationId = $('#class_location').val();
                let lecType = $('#lec_type').val();
                let editIndex = $('#edit_index').val();
                let slotId = $('#slot_id').val();

                if (!startTime || !endTime || !classId || !subjectId || !facultyId || !locationId || !lecType) {
                    Swal.fire('Error', 'Please fill all fields', 'error');
                    return;
                }

                let slot = {
                    start_time: startTime,
                    end_time: endTime,
                    class_id: classId,
                    subject_id: subjectId,
                    faculty_id: facultyId,
                    location_id: locationId,
                    lec_type: lecType,
                    classname: $('#class_batch option:selected').data('classname'),
                    batch: $('#class_batch option:selected').data('batch'),
                    subject_name: $('#subject option:selected').text(),
                    faculty_name: $('#faculty option:selected').text(),
                    saved: !!slotId,
                    id: slotId
                };

                Swal.fire({
                    title: 'Are you sure?',
                    text: slotId ? 'Do you want to update this slot?' : editIndex !== '' ? 'Do you want to save the changes to this slot?' : 'Do you want to add this slot?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, save it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (slotId) {
                            $.ajax({
                                url: 'update_slot.php',
                                method: 'POST',
                                data: {
                                    id: slotId,
                                    day: day,
                                    start_time: startTime,
                                    end_time: endTime,
                                    subject_id: subjectId,
                                    faculty_id: facultyId,
                                    class_id: classId,
                                    location_id: locationId,
                                    sem_id: selectedSemId,
                                    lec_type: lecType
                                },
                                success: function(response) {
                                    let res = JSON.parse(response);
                                    if (res.status === 'success') {
                                        loadTimetable(selectedSemId, selectedClassId);
                                        $('#slot_form').addClass('hidden');
                                        Swal.fire('Success', 'Slot updated successfully!', 'success');
                                    } else {
                                        Swal.fire('Error', res.message, 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('update_slot AJAX error:', status, error, 'Response:', xhr.responseText);
                                    Swal.fire('Error', 'Failed to delete slot. Please try again.', 'error');
                                }
                            });
                        } else if (editIndex !== '') {
                            slots[day][editIndex] = slot;
                            updateTiles(day);
                            $('#slot_form').addClass('hidden');
                            Swal.fire('Success', 'Slot updated successfully!', 'success');
                        } else {
                            slots[day].push(slot);
                            updateTiles(day);
                            $('#slot_form').addClass('hidden');
                            Swal.fire('Success', 'Slot added successfully!', 'success');
                        }
                    }
                });
            });

            // Edit Slot
            $(document).on('click', '.edit-slot', function() {
                let day = $(this).data('day');
                let index = $(this).data('index');
                let slot = $(this).hasClass('saved-slot') ? savedSlots[day][index] : slots[day][index];

                $('#slot_day').val(day);
                $('#edit_index').val($(this).hasClass('saved-slot') ? '' : index);
                $('#slot_id').val(slot.id || '');
                $('#start_time').val(slot.start_time);
                $('#end_time').val(slot.end_time);
                $('#subject').html('<option value="" disabled selected>Select Subject</option>').prop('disabled', true);
                $('#faculty').html('<option value="" disabled selected>Select Faculty</option>').prop('disabled', true);
                $('#class_location').val(slot.location_id);
                $('#lec_type').val(slot.lec_type);

                loadSubjects(true, slot.subject_id);

                $('#slot_form').removeClass('hidden');
                $('#save_slot').text(slot.id ? 'Update Slot' : 'Save Slot');
            });

            // Update Tiles
            function updateTiles(day) {
                let tiles = $('#tiles_' + day);
                tiles.empty();

                function formatTime(timeStr) {
                    if (!timeStr) return '';
                    const [hours, minutes] = timeStr.split(':');
                    const date = new Date();
                    date.setHours(parseInt(hours), parseInt(minutes));
                    return date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                }

                const sortedSavedSlots = savedSlots[day].slice().sort((a, b) => {
                    return a.start_time.localeCompare(b.start_time);
                });
                const sortedSlots = slots[day].slice().sort((a, b) => {
                    return a.start_time.localeCompare(b.start_time);
                });

                sortedSavedSlots.forEach((slot, index) => {
                    let subjectName = slot.subject_name || $('#subject option[value="' + slot.subject_id + '"]').text();
                    let facultyName = slot.faculty_name || $('#faculty option[value="' + slot.faculty_id + '"]').text();
                    let locationName = $('#class_location option[value="' + slot.location_id + '"]').text();
                    let className = slot.classname + (slot.batch ? ' - ' + slot.batch.toUpperCase() : '');
                    tiles.append(`
                        <div class="tile">
                            <span class="saved-tag saved">Saved</span>
                            <div class="grid grid-cols-2 gap-4 text-left">
                                <div>
                                    <p><strong>Time:</strong> ${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</p>
                                    <p><strong>Class:</strong> ${className}</p>
                                    <p><strong>Subject:</strong> ${subjectName} (${slot.lec_type === 'T' ? 'Theory' : 'Lab'})</p>
                                    <p><strong>Faculty:</strong> ${facultyName}</p>
                                    <p><strong>Location:</strong> ${locationName}</p>
                                </div>
                                <div class="flex items-end">
                                    <p>
                                        <button type="button" class="edit-slot saved-slot bg-cyan-600 text-white px-2 py-1 rounded-full hover:bg-cyan-700 font-bold transition-all" data-day="${day}" data-index="${index}">Edit</button>
                                        <button type="button" class="delete-slot saved-slot bg-red-500 text-white px-2 py-1 rounded-full hover:bg-red-600 font-bold transition-all ml-2" data-day="${day}" data-index="${index}" data-slot-id="${slot.id}">Delete</button>
                                    </p>
                                </div>
                            </div>
                        </div>
                    `);
                });

                sortedSlots.forEach((slot, index) => {
                    let subjectName = slot.subject_name || $('#subject option[value="' + slot.subject_id + '"]').text();
                    let facultyName = slot.faculty_name || $('#faculty option[value="' + slot.faculty_id + '"]').text();
                    let locationName = $('#class_location option[value="' + slot.location_id + '"]').text();
                    let className = slot.classname + (slot.batch ? ' - ' + slot.batch.toUpperCase() : '');
                    tiles.append(`
                        <div class="tile">
                            <span class="saved-tag not-saved">Not Saved</span>
                            <div class="grid grid-cols-2 gap-4 text-left">
                                <div>
                                    <p><strong>Time:</strong> ${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</p>
                                    <p><strong>Class:</strong> ${className}</p>
                                    <p><strong>Subject:</strong> ${subjectName} (${slot.lec_type === 'T' ? 'Theory' : 'Lab'})</p>
                                    <p><strong>Faculty:</strong> ${facultyName}</p>
                                    <p><strong>Location:</strong> ${locationName}</p>
                                </div>
                                <div class="flex items-end">
                                    <p>
                                        <button type="button" class="edit-slot bg-cyan-600 text-white px-2 py-1 rounded-full hover:bg-cyan-700 font-bold transition-all" data-day="${day}" data-index="${index}">Edit</button>
                                        <button type="button" class="delete-slot bg-red-500 text-white px-2 py-1 rounded-full hover:bg-red-600 font-bold transition-all ml-2" data-day="${day}" data-index="${index}">Delete</button>
                                    </p>
                                </div>
                            </div>
                        </div>
                    `);
                });
            }

            // Delete Slot
            $(document).on('click', '.delete-slot', function() {
                let day = $(this).data('day');
                let index = $(this).data('index');
                let slotId = $(this).data('slot-id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to delete this slot?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (slotId) {
                            $.ajax({
                                url: 'delete_slot.php',
                                method: 'POST',
                                data: {
                                    id: slotId
                                },
                                success: function(response) {
                                    let res = JSON.parse(response);
                                    if (res.status === 'success') {
                                        loadTimetable(selectedSemId, selectedClassId);
                                        Swal.fire('Success', 'Slot deleted successfully!', 'success');
                                    } else {
                                        Swal.fire('Error', res.message, 'error');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('delete_slot AJAX error:', status, error, 'Response:', xhr.responseText);
                                    Swal.fire('Error', 'Failed to delete slot. Please try again.', 'error');
                                }
                            });
                        } else {
                            slots[day].splice(index, 1);
                            updateTiles(day);
                            Swal.fire('Success', 'Slot deleted successfully!', 'success');
                        }
                    }
                });
            });

            // Submit Timetable
            $('#submit_timetable').click(function() {
                if (!selectedSemId || !selectedClassId) {
                    Swal.fire('Error', 'Please select semester and class', 'error');
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to submit the timetable?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, submit it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'save_timetable.php',
                            method: 'POST',
                            data: {
                                sem_id: selectedSemId,
                                class_id: selectedClassId,
                                slots: JSON.stringify(slots)
                            },
                            success: function(response) {
                                let res = JSON.parse(response);
                                if (res.status === 'success') {
                                    Swal.fire('Success', 'Timetable saved successfully!', 'success');
                                    loadTimetable(selectedSemId, selectedClassId);
                                } else {
                                    Swal.fire('Error', res.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('save_timetable AJAX error:', status, error, 'Response:', xhr.responseText);
                                Swal.fire('Error', 'Failed to save timetable. Please try again.', 'error');
                            }
                        });
                    }
                });
            });

            // Load Faculty and Configure Lecture Type on Subject Change
            $('#subject').change(function() {
                let subjectId = $(this).val();
                if (subjectId) {
                    loadFaculty(subjectId, selectedClassId);
                    let $selectedOption = $('#subject option:selected');
                    let lecType = $selectedOption.data('lec-type');
                    configureLectureType(lecType);
                } else {
                    $('#faculty').html('<option value="" disabled selected>Select Faculty</option>').prop('disabled', true);
                    $('#lec_type').val('').prop('disabled', false);
                }
            });
        });
    </script>
</body>

</html>