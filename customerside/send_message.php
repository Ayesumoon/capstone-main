<?php
// send_message.php
session_start();
require_once 'db_connect.php'; // adjust to your actual connection filename

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if (empty($message)) {
        echo "<script>alert('Please enter your message.'); window.history.back();</script>";
        exit;
    }

    // Check if user is logged in
    if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
        $customer_id = $_SESSION['customer_id'];

        // Get customer details
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();

        if (!$customer) {
            echo "<script>alert('Error: Unable to find customer details.'); window.history.back();</script>";
            exit;
        }

        $name = $customer['first_name'] . ' ' . $customer['last_name'];
        $email = $customer['email'];
    } else {
        // For guests (not logged in)
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            echo "<script>alert('Please enter your name and email.'); window.history.back();</script>";
            exit;
        }
    }

    // Get store email
    $storeQuery = $conn->query("SELECT store_email FROM store_settings LIMIT 1");
    $storeData = $storeQuery->fetch_assoc();

    if (!$storeData) {
        echo "<script>alert('Error: Store email not found.'); window.history.back();</script>";
        exit;
    }

    $store_email = $storeData['store_email'];

    // Prepare email content
    $subject = "New Message from $name - Seven Dwarfs Boutique";
    $body = "
        <h3>New Message Received:</h3>
        <p><strong>From:</strong> $name ($email)</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
    ";

    // Headers
    $headers = "From: {$email}\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Send the email
    if (mail($store_email, $subject, $body, $headers)) {
        echo "<script>alert('Thank you! Your message has been sent.'); window.location.href='contact.php';</script>";
    } else {
        echo "<script>alert('Sorry, message could not be sent. Please try again later.'); window.location.href='contact.php';</script>";
    }
}
?>
