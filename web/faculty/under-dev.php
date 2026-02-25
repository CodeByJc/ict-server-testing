<?php
include('../../api/db/db_connection.php');?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Development</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 flex h-screen overflow-hidden">
            <?php include('./sidebar.php'); ?>
            <div class="main-content pl-64 flex-1 ml-1/6 overflow-y-auto">
            <?php
            $page_title = "Under Developement";
            include('./navbar.php');
            ?>
            <div class="main-content pt-20 flex-1 ml-1/6 flex flex-col justify-center items-center text-center">
        
        <img src="../assets/images/web_under_dev.png" alt="Under Development" class="w-60 h-60 mb-4">
        <h1 class="text-3xl font-bold text-gray-700">Under Development</h1>
        <p class="text-lg text-gray-600 mt-2">This page is currently under development. Please check back later.</p>
    </div>
</body>
</html>
