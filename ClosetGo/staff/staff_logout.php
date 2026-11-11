<?php
session_start();
// Clear only staff-specific session keys (preserves user/admin sessions)
unset(
    $_SESSION['staff_logged_in'],
    $_SESSION['staff_id'],
    $_SESSION['staff_name'],
    $_SESSION['staff_email'],
    $_SESSION['staff_email_verified'],
    $_SESSION['staff_verified_at']
);
header('Location: ../login_user/login.php');
exit;


