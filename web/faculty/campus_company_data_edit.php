<?php
include('../../api/db/db_connection.php');

// Fetch companies, batches, job profiles, and rounds
function getCompanies() {
    global $conn;
    $res = mysqli_query($conn, "SELECT id, company_name FROM company_info ORDER BY company_name ASC");
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r;
    return $arr;
}
function getBatches() {
    global $conn;
    $res = mysqli_query($conn, "SELECT id, batch_start_year, batch_end_year FROM batch_info ORDER BY batch_start_year ASC");
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r;
    return $arr;
}
function getJobProfiles() {
    global $conn;
    $res = mysqli_query($conn, "SELECT profile_id, profile_name FROM job_profiles ORDER BY profile_name ASC");
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r;
    return $arr;
}
function getCompanyRoundsOptions() {
    global $conn;
    $res = mysqli_query($conn, "SELECT round_id, round_name FROM company_rounds ORDER BY round_name ASC");
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r;
    return $arr;
}
function getPlacement($drive_id) {
    global $conn;
    $q = "SELECT cpi.*, cmi.company_name, bi.id as batch_id FROM campus_placement_info cpi 
          JOIN company_info cmi ON cpi.company_info_id=cmi.id
          JOIN batch_info bi ON cpi.batch_info_id=bi.id
          WHERE cpi.id=".intval($drive_id);
    return mysqli_fetch_assoc(mysqli_query($conn,$q));
}
function getPlacementJobProfiles($drive_id) {
    global $conn;
    $res = mysqli_query($conn,"SELECT job_profile_id FROM campus_placement_job_profiles WHERE campus_placement_info_id=".intval($drive_id));
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r['job_profile_id'];
    return $arr;
}
function getPlacementRounds($drive_id) {
    global $conn;
    $res = mysqli_query($conn,"SELECT cri.id, cri.company_round_id, cri.mode, cri.round_index, cr.round_name
                               FROM company_rounds_info cri
                               JOIN company_rounds cr ON cr.round_id = cri.company_round_id
                               WHERE cri.campus_placement_info_id=".intval($drive_id)." ORDER BY cri.round_index");
    $arr = []; while($r = mysqli_fetch_assoc($res)) $arr[] = $r;
    return $arr;
}

$companies = getCompanies();
$batches = getBatches();
$jobProfiles = getJobProfiles();
$companyRoundsOptions = getCompanyRoundsOptions();

if(!isset($_GET['drive_id'])) die("Drive ID missing");
$drive_id = intval($_GET['drive_id']);
$placement = getPlacement($drive_id);
$selectedProfiles = getPlacementJobProfiles($drive_id);
$existingRounds = getPlacementRounds($drive_id);

// Handle POST update
if($_SERVER['REQUEST_METHOD']=='POST'){
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $package = mysqli_real_escape_string($conn, $_POST['package']);
    $other_info = mysqli_real_escape_string($conn, $_POST['other_info']);
    $batch_id = intval($_POST['batch_id']);
    $date_sql = !empty($_POST['date']) ? "'".mysqli_real_escape_string($conn,$_POST['date'])."'" : "NULL";
    $time_sql = !empty($_POST['time']) ? "'".mysqli_real_escape_string($conn,$_POST['time'])."'" : "NULL";

    mysqli_begin_transaction($conn);
    try{
        mysqli_query($conn,"UPDATE campus_placement_info SET date=$date_sql, time=$time_sql, location='$location', package='$package', other_info='$other_info', batch_info_id=$batch_id WHERE id=$drive_id");

        // Job profiles
        mysqli_query($conn,"DELETE FROM campus_placement_job_profiles WHERE campus_placement_info_id=$drive_id");
        if(!empty($_POST['job_profile_ids'])){
            $vals = [];
            foreach($_POST['job_profile_ids'] as $pid) $vals[] = "($drive_id,".intval($pid).")";
            mysqli_query($conn,"INSERT INTO campus_placement_job_profiles(campus_placement_info_id,job_profile_id) VALUES ".implode(",",$vals));
        }

        // Rounds
        mysqli_query($conn,"DELETE FROM company_rounds_info WHERE campus_placement_info_id=$drive_id");
        if(!empty($_POST['round_ids'])){
            $vals = [];
            foreach($_POST['round_ids'] as $k=>$rid){
                $rid_int=intval($rid);
                $mode = isset($_POST['round_modes'][$k]) && $_POST['round_modes'][$k]=='online'?'online':'offline';
                $idx = isset($_POST['round_index'][$k])?intval($_POST['round_index'][$k]):($k+1);
                $vals[] = "($drive_id,$rid_int,'$mode',$idx)";
            }
            mysqli_query($conn,"INSERT INTO company_rounds_info(campus_placement_info_id,company_round_id,mode,round_index) VALUES ".implode(",",$vals));
        }

        mysqli_commit($conn);
        echo "<script>Swal.fire({title:'Updated!',text:'Campus drive updated successfully.',icon:'success'}).then(()=>{window.location.href='campus_drive.php'});</script>";
        exit;
    }catch(Exception $e){
        mysqli_rollback($conn);
        echo "<script>Swal.fire({title:'Error',text:'".$e->getMessage()."',icon:'error'});</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Campus Drive</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
<?php include('./sidebar.php'); ?>
<div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
<?php $page_title="Edit Campus Drive"; include('./navbar.php'); ?>
<div class="container mx-auto p-6">
<a href="javascript:history.back()" class="text-white bg-gray-700 p-2 px-5 rounded-full mb-4 hover:px-7 inline-block transition-all"><i class="fa-solid fa-angle-left"></i> Back</a>

<form id="editForm" method="POST" class="bg-white p-6 rounded-xl shadow-md">
    <!-- Company Name (read only) -->
    <div class="w-full md:w-1/2 mb-4">
        <label class="block text-gray-700 font-bold mb-2">Company</label>
        <input type="text" class="w-full p-3 border-2 rounded-xl bg-gray-200" readonly value="<?php echo htmlspecialchars($placement['company_name']); ?>">
    </div>

    <div class="flex flex-wrap -mx-3">
        <div class="w-full md:w-1/6 px-3 mb-4">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo $placement['date']; ?>" class="w-full p-3 border-2 rounded-xl">
        </div>
        <div class="w-full md:w-1/6 px-3 mb-4">
            <label>Time</label>
            <input type="time" name="time" value="<?php echo $placement['time']; ?>" class="w-full p-3 border-2 rounded-xl">
        </div>
        <div class="w-full md:w-1/6 px-3 mb-4">
            <label>Batch</label>
            <select name="batch_id" class="w-full p-3 border-2 rounded-xl" required>
                <?php foreach($batches as $b): ?>
                <option value="<?php echo $b['id'];?>" <?php echo ($b['id']==$placement['batch_id'])?'selected':'';?>><?php echo $b['batch_start_year'].'-'.$b['batch_end_year'];?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="w-full md:w-1/2 px-3 mb-4">
            <label>Location</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($placement['location']);?>" class="w-full p-3 border-2 rounded-xl" required>
        </div>
    </div>

    <!-- Job Profiles -->
    <div class="w-full md:w-1/2 mb-4">
        <label>Job Profiles</label>
        <div class="border-2 rounded-xl p-3 max-h-64 overflow-y-auto">
        <?php foreach($jobProfiles as $jp): ?>
            <label class="flex items-center mb-2">
                <input type="checkbox" name="job_profile_ids[]" value="<?php echo $jp['profile_id'];?>" <?php echo in_array($jp['profile_id'],$selectedProfiles)?'checked':'';?> class="mr-2">
                <span><?php echo htmlspecialchars($jp['profile_name']);?></span>
            </label>
        <?php endforeach;?>
        </div>
    </div>

    <!-- Package -->
    <div class="w-full md:w-1/2 mb-4">
        <label>Package</label>
        <textarea name="package" rows="3" class="w-full p-3 border-2 rounded-xl"><?php echo htmlspecialchars($placement['package']);?></textarea>
    </div>

    <!-- Rounds -->
    <div class="w-full md:w-1/2 mb-4">
        <label>Selection Rounds</label>
        <div id="roundsContainer"></div>
        <button type="button" id="addRoundBtn" class="bg-cyan-500 text-sm font-bold text-white ml-10 px-3 p-1 rounded-full hover:scale-110 hover:bg-cyan-600 transition-all mt-3 mb-2">+ New Round</button>
    </div>

    <!-- Other Info -->
    <div class="mb-4">
        <label>Other Info</label>
        <textarea name="other_info" rows="5" class="w-full p-3 border-2 rounded-xl"><?php echo htmlspecialchars($placement['other_info']);?></textarea>
    </div>

    <button type="button" id="saveBtn" class="bg-cyan-600 text-white px-5 p-3 rounded-full hover:px-7 font-bold hover:bg-cyan-700 transition-all">Save Changes</button>
</form>
</div>
</div>

<script>
const companyRounds = <?php echo json_encode($companyRoundsOptions); ?>;
let roundCount=0;

// Load existing rounds
const existingRounds = <?php echo json_encode($existingRounds); ?>;
const container=document.getElementById('roundsContainer');
existingRounds.forEach(r=>{
    roundCount++;
    let options='';
    companyRounds.forEach(co=>{options+=`<option value="${co.round_id}" ${co.round_id==r.company_round_id?'selected':''}>${co.round_name}</option>`});
    const html=`<div class="flex items-center ml-4 mb-2" id="round-${roundCount}">
        <span class="mr-2 text-cyan-600 font-bold">${roundCount}.</span>
        <select name="round_ids[]" class="p-2 border-2 rounded-xl flex-1">${options}</select>
        <select name="round_modes[]" class="p-2 border-2 rounded-xl ml-2">
            <option value="offline" ${r.mode=='offline'?'selected':''}>Offline</option>
            <option value="online" ${r.mode=='online'?'selected':''}>Online</option>
        </select>
        <input type="hidden" name="round_index[]" value="${r.round_index}">
        <button type="button" class="text-red-500 ml-3" onclick="removeRound(${roundCount})"><i class="fa-solid fa-trash"></i></button>
    </div>`;
    container.insertAdjacentHTML('beforeend',html);
});

document.getElementById('addRoundBtn').addEventListener('click',()=>{
    roundCount++;
    let options='';
    companyRounds.forEach(co=>{options+=`<option value="${co.round_id}">${co.round_name}</option>`});
    const html=`<div class="flex items-center ml-4 mb-2" id="round-new-${roundCount}">
        <span class="mr-2 text-cyan-600 font-bold">${roundCount}.</span>
        <select name="round_ids[]" class="p-2 border-2 rounded-xl flex-1">${options}</select>
        <select name="round_modes[]" class="p-2 border-2 rounded-xl ml-2">
            <option value="offline">Offline</option>
            <option value="online">Online</option>
        </select>
        <input type="hidden" name="round_index[]" value="${roundCount}">
        <button type="button" class="text-red-500 ml-3" onclick="removeRound('new-${roundCount}')"><i class="fa-solid fa-trash"></i></button>
    </div>`;
    container.insertAdjacentHTML('beforeend',html);
});

function removeRound(id){
    const el=document.getElementById(`round-${id}`) || document.getElementById(`round-${id}`);
    if(el) el.remove();
    updateIndices();
}

function updateIndices(){
    const rounds=document.querySelectorAll('#roundsContainer > div');
    let idx=1;
    rounds.forEach(r=>{
        const span=r.querySelector('span');
        const input=r.querySelector('input[name="round_index[]"]');
        if(span) span.textContent=idx+'.';
        if(input) input.value=idx;
        idx++;
    });
}

document.getElementById('saveBtn').addEventListener('click',()=>{
    Swal.fire({
        title:'Are you sure?',
        text:"Do you want to save the changes?",
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#3085d6',
        cancelButtonColor:'#d33',
        confirmButtonText:'Yes, save it!'
    }).then((result)=>{
        if(result.isConfirmed) document.getElementById('editForm').submit();
    });
});
</script>
</body>
</html>
