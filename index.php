<?php
session_start();
header('Location: ' . (!empty($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
exit;