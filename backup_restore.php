<?php
session_start();
require 'conn.php';

// Restrict to admins only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit;
}

$backupFile = 'backups/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
$backupDir = 'backups/';

if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

// --- Handle Backup ---
if (isset($_POST['backup'])) {
    $command = "mysqldump --user=root --password= --host=localhost dbms > $backupFile";
    exec($command, $output, $result);
    $message = ($result === 0) ? "Backup created: $backupFile" : "Backup failed.";
}

// --- Handle Restore ---
if (isset($_POST['restore'])) {
    $fileToRestore = $_FILES['backup_file']['tmp_name'];
    $command = "mysql --user=root --password= --host=localhost dbms < $fileToRestore";
    exec($command, $output, $result);
    $message = ($result === 0) ? "Database restored successfully." : "Restore failed.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backup & Restore | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-10 font-poppins">
<div class="max-w-xl mx-auto bg-white p-6 rounded-xl shadow space-y-5">
  <h1 class="text-2xl font-semibold text-[var(--rose)] mb-4">Backup & Restore</h1>

  <?php if (!empty($message)): ?>
    <div class="bg-blue-100 text-blue-700 p-2 rounded"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST">
    <button name="backup" class="bg-[var(--rose)] text-white px-4 py-2 rounded hover:bg-[var(--rose-hover)] w-full">Create Backup</button>
  </form>

  <form method="POST" enctype="multipart/form-data">
    <label class="block text-gray-700 font-medium mt-4">Restore from Backup (.sql)</label>
    <input type="file" name="backup_file" accept=".sql" required class="border p-2 rounded w-full">
    <button name="restore" class="mt-3 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 w-full">Restore</button>
  </form>
</div>
</body>
</html>
