<?php
session_start();
include 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = $_POST['email'];
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  
  
  if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password_hash'])) {
      $_SESSION['customer_id'] = $user['customer_id'];
      $_SESSION['customer_name'] = $user['first_name'];

      // ✅ Redirect to homepage or dashboard
      header("Location: homepage.php");
      exit();
    } else {
      // ❌ Wrong password
      header("Location: login.php?error=Incorrect+password");
      exit();
    }
  } else {
    // ❌ Email not found
    header("Location: login.php?error=User+not+found");
    exit();
  }
}
?>
