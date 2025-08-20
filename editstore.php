<?php
session_start();
include 'conn.php'; // Include your database connection file

// Check if user is logged in (optional check)
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch store settings data from the database
$sql = "SELECT * FROM store_settings WHERE id = 1"; // Assuming you have one record for store settings
$result = mysqli_query($conn, $sql);
$store_settings = mysqli_fetch_assoc($result); // Fetch the data as an associative array

// Check if form is submitted to update the settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input values to prevent XSS or SQL injection
    $store_name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $store_description = mysqli_real_escape_string($conn, $_POST['store_description']);
    $store_email = mysqli_real_escape_string($conn, $_POST['store_email']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $timezone_locale = mysqli_real_escape_string($conn, $_POST['timezone_locale']);
    $theme = mysqli_real_escape_string($conn, $_POST['theme']);
    $homepage_layout = mysqli_real_escape_string($conn, $_POST['homepage_layout']);
    $custom_css_html = mysqli_real_escape_string($conn, $_POST['custom_css_html']);

    // Update store settings in the database
    $update_sql = "UPDATE store_settings SET 
                    store_name = '$store_name',
                    store_description = '$store_description',
                    store_email = '$store_email',
                    contact = '$contact',
                    address = '$address',
                    timezone_locale = '$timezone_locale',
                    theme = '$theme',
                    homepage_layout = '$homepage_layout',
                    custom_css_html = '$custom_css_html'
                    WHERE id = 1";

    if (mysqli_query($conn, $update_sql)) {
        // Redirect to the store settings page after successful update
        header("Location: storesettings.php");
        exit();
    } else {
        // Handle any errors during the update
        echo "Error updating settings: " . mysqli_error($conn);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Store Settings</title>
    <link rel="stylesheet" href="css/editstore.css">
</head>
<body>
    <div class="sidebar">
        <h2>Dashboard</h2>
        <p>Welcome back, <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?>!</p>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="customers.php">Customers</a></li>
                <li><a href="inventory.php">Inventory</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="payandtransac.php">Payment & Transactions</a></li>
                <li><strong><a href="storesettings.php">Store Settings</a></strong></li>
                <li><a href="login.php">Log out</a></li>
            </ul>
        </nav>
    </div>
    <div class="main-content">
        <h1>Edit Store Settings</h1>
        <div class="settings-container">
            <!-- General Store Information Section -->
            <div class="section">
                <h2>General Store Information</h2>
                <form method="POST" action="">
                    <div class="settings-group">
                        <label>Store Name:</label>
                        <input type="text" name="store_name" value="<?php echo htmlspecialchars($store_settings['store_name']); ?>" required>
                    </div>
                    <div class="settings-group">
                        <label>Store Description:</label>
                        <textarea name="store_description" required><?php echo htmlspecialchars($store_settings['store_description']); ?></textarea>
                    </div>
                    <div class="settings-group">
                        <label>Store Email:</label>
                        <input type="email" name="store_email" value="<?php echo htmlspecialchars($store_settings['store_email']); ?>" required>
                    </div>
                    <div class="settings-group">
                        <label>Contact:</label>
                        <input type="text" name="contact" value="<?php echo htmlspecialchars($store_settings['contact']); ?>" required>
                    </div>
                    <div class="settings-group">
                        <label>Address:</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($store_settings['address']); ?>" required>
                    </div>
                    <div class="settings-group">
                        <label>Timezone & Locale:</label>
                        <input type="text" name="timezone_locale" value="<?php echo htmlspecialchars($store_settings['timezone_locale']); ?>" required>
                    </div>
            </div>

            <!-- Theme & Design Section -->
            <div class="section">
                <h2>Theme & Design</h2>
                <div class="settings-group">
                    <label for="theme">Current Theme:</label>
                    <select name="theme" id="theme" onchange="setTheme(this.value)" required>
                        <option value="light" <?php echo $store_settings['theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo $store_settings['theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                        <option value="system" <?php echo $store_settings['theme'] == 'system' ? 'selected' : ''; ?>>System Default</option>
                    </select>
                </div>
                    <div class="settings-group">
                        <label>Homepage Layout:</label>
                        <input type="text" name="homepage_layout" value="<?php echo htmlspecialchars($store_settings['homepage_layout']); ?>" required>
                    </div>
            </div>

            <!-- Save Button -->
            <div class="settings-group">
                <button type="submit" class="save-btn">Save Changes</button>
                <button type="button" class="cancel-btn" onclick="window.location.href='storesettings.php'">Cancel</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>

<script>
    // Function to apply the selected theme
    function setTheme(theme) {
        // Save the selected theme in localStorage
        localStorage.setItem('theme', theme);

        // Apply the theme
        document.documentElement.setAttribute('data-theme', theme);

        // Optionally reload page to ensure theme is applied
        location.reload();
    }

    // Load the theme from localStorage if set previously
    window.onload = function() {
        const savedTheme = localStorage.getItem('theme') || 'system';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.getElementById('theme').value = savedTheme; // Set dropdown to current theme
    };
</script>

<?php
// Close the database connection
mysqli_close($conn);
?>
