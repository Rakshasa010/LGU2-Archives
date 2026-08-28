<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_destroy();
    header("Location: login.php");
    exit();
} else {
    header("Location: archives-landing.php");
    exit();
}
?>
