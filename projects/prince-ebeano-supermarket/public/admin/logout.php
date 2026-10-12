<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

if (is_post()) {
    csrf_verify();
    logout();
    start_session();
    flash('success', 'You are logged out. See you tomorrow!');
}
redirect(url('admin/login.php'));
