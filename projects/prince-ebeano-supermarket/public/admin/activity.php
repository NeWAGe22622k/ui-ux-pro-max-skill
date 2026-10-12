<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_login();
[$scopeBranch, $scopeCategory] = staff_scope();
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];
if ($scopeBranch !== null) {
    $where[] = 'u.branch_id = ? AND p.category_id = ?';
    array_push($params, $scopeBranch, $scopeCategory);
} elseif (($filter = (int)($_GET['branch'] ?? 0)) > 0) {
    $where[] = 'u.branch_id = ?';
    $params[] = $filter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)q_val("SELECT COUNT(*) FROM stock_updates u JOIN products p ON p.id = u.product_id $whereSql", $params);
$rows = q_all(
    "SELECT u.*, p.name AS product_name, b.name AS branch_name, c.name AS category_name, a.username
     FROM stock_updates u
     JOIN products p ON p.id = u.product_id
     JOIN categories c ON c.id = p.category_id
     JOIN branches b ON b.id = u.branch_id
     LEFT JOIN accounts a ON a.id = u.account_id
     $whereSql
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$pages = max(1, (int)ceil($total / $perPage));

function status_cell(?string $status): string
{
    return $status === null ? status_pill('not_carried') : status_pill($status);
}

admin_start('Activity log', 'activity');
?>
<div class="admin-head">
  <div>
    <h1>Activity log</h1>
    <p>Every stock and price change, newest first<?= $scopeBranch !== null ? ' — for your store and aisle' : '' ?>.</p>
  </div>
  <?php if ($scopeBranch === null): ?>
    <form method="get" class="scope-bar" style="margin:0">
      <div class="field">
        <label for="a-branch">Store</label>
        <select class="select" id="a-branch" name="branch" data-autosubmit>
          <option value="">All stores</option>
          <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= (int)($_GET['branch'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <noscript><button class="btn btn-primary" type="submit">Filter</button></noscript>
    </form>
  <?php endif; ?>
</div>

<?php if (!$rows): ?>
  <?= empty_state('history', 'No changes yet', 'Changes appear here as soon as staff save a morning update.') ?>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>When</th><th>Product</th><th>Store</th><th>Stock</th><th>Price</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="white-space:nowrap"><?= e(date('D j M, g:ia', strtotime($r['created_at']))) ?></td>
            <td><strong><?= e($r['product_name']) ?></strong><br><small class="muted"><?= e($r['category_name']) ?></small></td>
            <td><?= e($r['branch_name']) ?></td>
            <td style="white-space:nowrap"><?= status_cell($r['old_status']) ?> → <?= status_cell($r['new_status']) ?></td>
            <td style="white-space:nowrap">
              <?php if ($r['old_price_kobo'] !== $r['new_price_kobo']): ?>
                <?= $r['old_price_kobo'] !== null ? e(naira((int)$r['old_price_kobo'])) : '—' ?> → <strong><?= $r['new_price_kobo'] !== null ? e(naira((int)$r['new_price_kobo'])) : '—' ?></strong>
              <?php else: ?><span class="muted">unchanged</span><?php endif; ?>
            </td>
            <td><?= e($r['staff_name'] ?: '—') ?><br><small class="muted"><?= e($r['username'] ?? 'deleted login') ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav style="display:flex;gap:.5rem;justify-content:center;margin-top:1rem" aria-label="Pages">
      <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(current_url_with(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
      <span class="btn btn-sm" style="cursor:default">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(current_url_with(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php admin_end();
