<?php
require __DIR__ . '/../includes/admin.php';
require_login();
check_csrf();

$list = valid_list((string) ($_POST['list'] ?? ''));
$id = (string) ($_POST['id'] ?? '');
$do = (string) ($_POST['do'] ?? '');

$items = load_json($list, []);
$i = find_index($items, $id);
if ($i === null) {
    flash('That item no longer exists.', 'error');
    header('Location: index.php?list=' . $list);
    exit;
}

$title = $items[$i]['title'] ?? 'Item';
$removed = [];

switch ($do) {
    case 'up':
    case 'down':
        $j = $do === 'up' ? $i - 1 : $i + 1;
        if (isset($items[$j])) {
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        break;
    case 'publish':
        $items[$i]['published'] = empty($items[$i]['published']);
        flash($title . ($items[$i]['published'] ? ' is now visible on the site.' : ' is now hidden from the site.'));
        break;
    case 'status':
        $items[$i]['status'] = ($items[$i]['status'] ?? '') === 'let' ? 'available' : 'let';
        flash($title . ' marked as ' . ($items[$i]['status'] === 'let' ? 'let agreed.' : 'available.'));
        break;
    case 'delete':
        foreach (['images', 'before', 'after'] as $k) $removed = array_merge($removed, $items[$i][$k] ?? []);
        array_splice($items, $i, 1);
        flash($title . ' deleted.');
        break;
    default:
        http_response_code(400);
        exit('Unknown action');
}

if (!save_json($list, array_values($items))) {
    flash('Could not save. Check that the /data folder is writable.', 'error');
} elseif ($removed) {
    remove_unused($removed);
}

header('Location: index.php?list=' . $list);
