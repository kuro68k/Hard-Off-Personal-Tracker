<?php
session_start();

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
?>
