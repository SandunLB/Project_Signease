<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "signease";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
