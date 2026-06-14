<?php
/**
 * admin_logout.php — End the admin session.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

admin_logout();

// Start a fresh session just to carry the goodbye flash message.
session_start();
flash('info', 'Uspješno ste odjavljeni.');
redirect('admin_login.php');
