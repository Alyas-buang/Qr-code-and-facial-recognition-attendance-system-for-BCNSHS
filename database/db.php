<?php
$conn = new mysqli("localhost", "root", "", "Aliviado_db");
if ($conn->connect_error) {
    die("Connection failed");
}
?>
