<?php
/** logout.php — end the visitor session. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
user_logout();
flash('info', 'Odjavljeni ste.');
redirect('index.php');
