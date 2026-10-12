<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_owner();

if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    match ($_POST['action'] ?? '') {
        'read'     => q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]),
        'unread'   => q('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$id]),
        'delete'   => q('DELETE FROM contact_messages WHERE id = ?', [$id]),
        'read_all' => q('UPDATE contact_messages SET is_read = 1'),
        default    => null,
    };
    redirect(current_url_with([]));
}

$show = ($_GET['show'] ?? '') === 'all' ? 'all' : 'unread';
$messages = q_all(
    'SELECT m.*, b.name AS branch_name FROM contact_messages m LEFT JOIN branches b ON b.id = m.branch_id '
    . ($show === 'unread' ? 'WHERE m.is_read = 0 ' : '') . 'ORDER BY m.created_at DESC LIMIT 200'
);

admin_start('Messages', 'messages');
?>
<div class="admin-head">
  <div><h1>Messages</h1><p>Sent from the Contact page.</p></div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a class="chip" href="<?= e(url('admin/messages.php')) ?>" aria-current="<?= $show === 'unread' ? 'true' : 'false' ?>">Unread</a>
    <a class="chip" href="<?= e(url('admin/messages.php?show=all')) ?>" aria-current="<?= $show === 'all' ? 'true' : 'false' ?>">All</a>
    <form method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" name="action" value="read_all">Mark all read</button></form>
  </div>
</div>

<?php if (!$messages): ?>
  <?= empty_state('inbox', $show === 'unread' ? 'You\'re all caught up' : 'No messages yet', 'New messages from the Contact page will appear here.') ?>
<?php endif; ?>

<?php foreach ($messages as $m): ?>
  <article class="card message-item<?= $m['is_read'] ? '' : ' is-unread' ?>">
    <header>
      <div>
        <strong><?= e($m['subject'] ?: '(no subject)') ?></strong>
        <div class="meta"><?= e($m['name']) ?>
          <?php if ($m['email']): ?> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= e($m['email']) ?></a><?php endif; ?>
          <?php if ($m['phone']): ?> · <a href="<?= e(phone_href($m['phone'])) ?>"><?= e($m['phone']) ?></a><?php endif; ?>
        </div>
      </div>
      <div class="meta"><?= e(date('D j M, g:ia', strtotime($m['created_at']))) ?><?= $m['branch_name'] ? ' · ' . e($m['branch_name']) : '' ?></div>
    </header>
    <p><?= e($m['message']) ?></p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="btn btn-ghost btn-sm" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
        <button class="btn btn-danger btn-sm" name="action" value="delete" data-confirm="Delete this message?"><?= icon('trash', 'icon icon-sm') ?> Delete</button></form>
    </div>
  </article>
<?php endforeach; ?>
<?php admin_end();
