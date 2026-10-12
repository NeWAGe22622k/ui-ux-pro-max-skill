<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$me = require_owner();

/** Easy-to-read-out password, e.g. "jollof-4821-mango". */
function friendly_password(): string
{
    $words = ['mango', 'pepper', 'yam', 'plantain', 'kola', 'zobo', 'suya', 'garri', 'okra', 'agege', 'puff', 'moimoi', 'jollof', 'egusi', 'ofada', 'chinchin', 'akara', 'tiger', 'palm', 'cocoa'];
    return $words[random_int(0, count($words) - 1)] . '-' . random_int(1000, 9999) . '-' . $words[random_int(0, count($words) - 1)];
}

function create_staff_login(int $branchId, int $categoryId, string $username, string $password): void
{
    q("INSERT INTO accounts (username, password_hash, role, branch_id, category_id) VALUES (?, ?, 'staff', ?, ?)",
        [$username, password_hash($password, PASSWORD_DEFAULT), $branchId, $categoryId]);
}

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $issued = [];   // logins + passwords to show once

    if ($action === 'create') {
        $branch = branch_by_id((int)($_POST['branch_id'] ?? 0));
        $category = category_by_id((int)($_POST['category_id'] ?? 0));
        $username = strtolower(post_str('username', 60));
        if (!$branch || !$category) {
            $errors[] = 'Please choose a store and an aisle.';
        }
        if ($username === '' && $branch && $category) {
            $username = $branch['slug'] . '-' . $category['slug'];
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/', $username)) {
            $errors[] = 'Usernames need 3+ characters: letters, numbers, dots or dashes.';
        } elseif (q_val('SELECT id FROM accounts WHERE username = ?', [$username])) {
            $errors[] = "The username “{$username}” is already taken.";
        }
        if (!$errors) {
            $password = friendly_password();
            create_staff_login((int)$branch['id'], (int)$category['id'], $username, $password);
            $issued[] = [$username, $password, $branch['name'] . ' · ' . $category['name']];
        }
    } elseif ($action === 'bulk') {
        $branch = branch_by_id((int)($_POST['branch_id'] ?? 0));
        if (!$branch) {
            $errors[] = 'Please choose a store.';
        } else {
            foreach (categories() as $c) {
                $exists = q_val('SELECT id FROM accounts WHERE branch_id = ? AND category_id = ?', [$branch['id'], $c['id']]);
                $username = $branch['slug'] . '-' . $c['slug'];
                if ($exists || q_val('SELECT id FROM accounts WHERE username = ?', [$username])) {
                    continue;
                }
                $password = friendly_password();
                create_staff_login((int)$branch['id'], (int)$c['id'], $username, $password);
                $issued[] = [$username, $password, $branch['name'] . ' · ' . $c['name']];
            }
            if (!$issued) {
                flash('info', "{$branch['name']} already has a login for every aisle.");
            }
        }
    } else {
        $target = q_one('SELECT * FROM accounts WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if (!$target || (int)$target['id'] === (int)$me['id']) {
            abort(400, 'You can\'t change your own owner login from here. Use “Change password” instead.');
        }
        if ($action === 'reset') {
            $password = friendly_password();
            q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $target['id']]);
            $issued[] = [$target['username'], $password, account_scope_label($target)];
        } elseif ($action === 'toggle') {
            q('UPDATE accounts SET is_active = 1 - is_active WHERE id = ?', [$target['id']]);
            flash('success', $target['is_active'] ? "Switched off {$target['username']}." : "Switched on {$target['username']}.");
        } elseif ($action === 'delete') {
            q('DELETE FROM accounts WHERE id = ?', [$target['id']]);
            flash('success', "Deleted the {$target['username']} login.");
        }
    }

    if (!$errors) {
        if ($issued) {
            $_SESSION['issued_logins'] = $issued;
        }
        redirect(url('admin/accounts.php'));
    }
}

$issuedNow = $_SESSION['issued_logins'] ?? [];
unset($_SESSION['issued_logins']);

$accounts = q_all(
    'SELECT a.*, b.name AS branch_name, c.name AS category_name
     FROM accounts a LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN categories c ON c.id = a.category_id
     ORDER BY a.role = \'owner\' DESC, b.sort_order, b.name, c.sort_order, c.name'
);

admin_start('Staff logins', 'accounts');
?>
<div class="admin-head">
  <div>
    <h1>Staff logins</h1>
    <p>One login per store + aisle — e.g. <strong>ikeja-drinks</strong> can only update Drinks at Ikeja.</p>
  </div>
</div>

<?php foreach ($errors as $err): ?><div class="flash flash-error" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>

<?php if ($issuedNow): ?>
  <div class="card" style="border:2px solid var(--mango);background:#FFFBF2;margin-bottom:1.5rem">
    <h2><?= icon('lock') ?> New passwords — copy them now</h2>
    <p>For safety these passwords are shown <strong>only once</strong>. Write them down or print this page, then hand each one to the right team.</p>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Store · aisle</th><th>Username</th><th>Password</th></tr></thead>
        <tbody>
          <?php foreach ($issuedNow as [$u, $p, $label]): ?>
            <tr><td><?= e($label) ?></td><td><code><?= e($u) ?></code></td><td><code style="font-size:1.05rem;font-weight:800"><?= e($p) ?></code></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin:1rem 0 0"><button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">Print</button></p>
  </div>
<?php endif; ?>

<div class="form-grid cols-2" style="margin-bottom:1.5rem;align-items:start">
  <form class="card" method="post">
    <h2>Add one login</h2>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-grid">
      <div class="field"><label for="n-branch">Store</label>
        <select class="select" id="n-branch" name="branch_id" required>
          <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="n-cat">Aisle</label>
        <select class="select" id="n-cat" name="category_id" required>
          <?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="n-user">Username</label>
        <input class="input" id="n-user" name="username" placeholder="Leave blank for store-aisle" autocapitalize="none"></div>
      <div><button class="btn btn-primary" type="submit"><?= icon('plus', 'icon icon-sm') ?> Create login</button></div>
    </div>
  </form>

  <form class="card" method="post">
    <h2>Set up a whole store</h2>
    <p class="muted">Creates a login for every aisle at one store (skips any that already exist) and shows all the passwords once.</p>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk">
    <div class="form-grid">
      <div class="field"><label for="bulk-branch">Store</label>
        <select class="select" id="bulk-branch" name="branch_id" required>
          <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><button class="btn btn-mango" type="submit"><?= icon('users', 'icon icon-sm') ?> Create all logins</button></div>
    </div>
  </form>
</div>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Username</th><th>Can update</th><th>Last login</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($accounts as $a): $isMe = (int)$a['id'] === (int)$me['id']; ?>
        <tr>
          <td><strong><?= e($a['username']) ?></strong><?= $isMe ? ' <small class="muted">(you)</small>' : '' ?></td>
          <td><?= $a['role'] === 'owner' ? 'Everything (owner)' : e(($a['branch_name'] ?? '?') . ' · ' . ($a['category_name'] ?? '?')) ?></td>
          <td><?= $a['last_login_at'] ? e(fresh_label($a['last_login_at'])) : '<span class="muted">never</span>' ?></td>
          <td><span class="status-dot<?= $a['is_active'] ? '' : ' off' ?>"><?= $a['is_active'] ? 'Active' : 'Switched off' ?></span></td>
          <td>
            <?php if (!$isMe): ?>
              <div class="actions">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="reset" data-confirm="Give <?= e($a['username']) ?> a new password? The old one stops working immediately.">New password</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="toggle"><?= $a['is_active'] ? 'Switch off' : 'Switch on' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-danger btn-sm" name="action" value="delete" data-confirm="Delete the <?= e($a['username']) ?> login?" aria-label="Delete <?= e($a['username']) ?>"><?= icon('trash', 'icon icon-sm') ?></button></form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_end();
