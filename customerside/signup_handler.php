<?php
include 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $first_name = $_POST['first_name'];
  $last_name = $_POST['last_name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $address = $_POST['address'];
  $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

  $check = $conn->prepare("SELECT * FROM customers WHERE email = ?");
  $check->bind_param("s", $email);
  $check->execute();
  $result = $check->get_result();

  if ($result->num_rows > 0) {
    // ❌ Email already used
    header("Location: signup.php?error=Email+already+registered");
    exit();
  } else {
    $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone, password_hash, address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone, $password, $address);

    if ($stmt->execute()) {
      // ✅ Redirect to login page or auto-login
      header("Location: homepage.php");
      exit();
    } else {
      header("Location: signup.php?error=Something+went+wrong");
      exit();
    }
  }
}
?>
