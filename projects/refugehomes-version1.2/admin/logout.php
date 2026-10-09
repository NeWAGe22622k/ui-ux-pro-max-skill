<?php
require __DIR__ . '/../includes/admin.php';
check_csrf();
$_SESSION = [];
session_destroy();
header('Location: index.php');
