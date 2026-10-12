<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

require_owner();

$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = $editId ? q_one('SELECT * FROM jobs WHERE id = ?', [$editId]) : null;
$values = $editing ?? ['title' => '', 'branch_id' => null, 'summary' => '', 'is_open' => 1];
$errors = [];

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'delete' && $editing) {
        q('DELETE FROM jobs WHERE id = ?', [$editing['id']]);
        flash('success', "Deleted “{$editing['title']}”.");
        redirect(url('admin/jobs.php'));
    }
    $values['title'] = post_str('title', 120);
    $values['summary'] = post_str('summary', 600);
    $bid = (int)($_POST['branch_id'] ?? 0);
    $values['branch_id'] = $bid && branch_by_id($bid) ? $bid : null;
    $values['is_open'] = (int)isset($_POST['is_open']);

    if ($values['title'] === '') {
        $errors[] = 'Please enter the job title.';
    }
    if (!$errors) {
        if ($editing) {
            q('UPDATE jobs SET title = ?, branch_id = ?, summary = ?, is_open = ? WHERE id = ?', [$values['title'], $values['branch_id'], $values['summary'], $values['is_open'], $editing['id']]);
        } else {
            q('INSERT INTO jobs (title, branch_id, summary, is_open) VALUES (?, ?, ?, ?)', [$values['title'], $values['branch_id'], $values['summary'], $values['is_open']]);
        }
        flash('success', "Saved “{$values['title']}”.");
        redirect(url('admin/jobs.php'));
    }
}

$jobs = q_all('SELECT j.*, b.name AS branch_name FROM jobs j LEFT JOIN branches b ON b.id = j.branch_id ORDER BY j.is_open DESC, j.posted_at DESC');

admin_start('Job openings', 'jobs');
?>
<div class="admin-head"><div><h1>Job openings</h1><p>These appear on the Careers page. Applicants apply by email to the address in Site settings.</p></div></div>

<?php if ($jobs): ?>
<div class="table-wrap" style="margin-bottom:1.5rem">
  <table class="table">
    <thead><tr><th>Role</th><th>Store</th><th>Posted</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr>
          <td><strong><?= e($j['title']) ?></strong></td>
          <td><?= e($j['branch_name'] ?? 'Any store') ?></td>
          <td><?= e(date('j M Y', strtotime($j['posted_at']))) ?></td>
          <td><span class="status-dot<?= $j['is_open'] ? '' : ' off' ?>"><?= $j['is_open'] ? 'Open' : 'Closed' ?></span></td>
          <td class="actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/jobs.php?edit=' . $j['id'])) ?>#form"><?= icon('pencil', 'icon icon-sm') ?> Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<form class="card" method="post" action="<?= e(url('admin/jobs.php')) ?>" id="form" style="max-width:760px">
  <h2><?= $editing ? 'Edit job' : 'Post a job' ?></h2>
  <?php foreach ($errors as $err): ?><div class="flash flash-error" style="margin-bottom:.5rem"><?= icon('circle-alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
  <div class="form-grid cols-2">
    <div class="field"><label for="j-title">Job title *</label><input class="input" id="j-title" name="title" value="<?= e($values['title']) ?>" placeholder="e.g. Sales Associate" required></div>
    <div class="field"><label for="j-branch">Store</label>
      <select class="select" id="j-branch" name="branch_id">
        <option value="">Any store</option>
        <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= (int)$values['branch_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field span-2"><label for="j-sum">Short description</label><textarea class="textarea" id="j-sum" name="summary" maxlength="600" style="min-height:100px"><?= e($values['summary']) ?></textarea></div>
    <div class="span-2"><label class="check"><input type="checkbox" name="is_open" value="1" <?= $values['is_open'] ? 'checked' : '' ?>> Accepting applications</label></div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
    <button class="btn btn-primary" type="submit" name="action" value="save"><?= $editing ? 'Save changes' : 'Post job' ?></button>
    <?php if ($editing): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/jobs.php')) ?>">Cancel</a>
      <button class="btn btn-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this job?"><?= icon('trash', 'icon icon-sm') ?> Delete</button>
    <?php endif; ?>
  </div>
</form>
<?php admin_end();
