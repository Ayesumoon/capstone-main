<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$first = $_POST['first_name'];
$last = $_POST['last_name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$address = $_POST['address'];

$stmt = $conn->prepare("UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE customer_id = ?");
$stmt->bind_param("sssssi", $first, $last, $email, $phone, $address, $user_id);

if ($stmt->execute()) {
  header("Location: profile.php?updated=1");
} else {
  echo "Error updating profile.";
}
?>
