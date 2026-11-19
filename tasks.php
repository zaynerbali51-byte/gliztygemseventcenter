<?php
require __DIR__ . '/../bootstrap.php';
// Event preparation tracker. Customers identify themselves with the verification code; booking must be Confirmed.
$m = $_SERVER['REQUEST_METHOD']; $b = $m === 'GET' ? $_GET : body();
$s = db()->prepare("SELECT booking_id FROM booking WHERE verification_code=? AND status='Confirmed'");
$s->execute([strtoupper(str($b, 'code', 12))]); $bid = (int)$s->fetchColumn();
if (!$bid) fail('Confirmed booking not found for that code.', 404);
const ST = ['Pending', 'In Progress', 'Completed'];
function progress(int $bid): array {
    $p = db()->prepare('SELECT total_tasks, completed_tasks, progress_percent FROM v_booking_progress WHERE booking_id=?'); $p->execute([$bid]); return $p->fetch();
}
function fields(array $b): array {
    $t = str($b, 'title', 200); if ($t === '') fail('Enter a task title.');
    $dl = str($b, 'deadline', 10) ?: null; if ($dl && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dl)) fail('Deadline must be a valid date.');
    $st = in_array($b['status'] ?? 'Pending', ST, true) ? $b['status'] : 'Pending';
    return [$t, $dl, $st];
}
if ($m === 'GET') {
    $l = db()->prepare('SELECT task_id, title, deadline, status FROM task WHERE booking_id=? ORDER BY deadline IS NULL, deadline, task_id'); $l->execute([$bid]);
    out(['tasks' => $l->fetchAll(), 'progress' => progress($bid)]);
}
if ($m === 'POST') { [$t, $dl, $st] = fields($b);
    db()->prepare('INSERT INTO task (booking_id,title,deadline,status) VALUES (?,?,?,?)')->execute([$bid, $t, $dl, $st]);
    out(['task_id' => (int)db()->lastInsertId(), 'progress' => progress($bid)], 201);
}
if ($m === 'PUT') { [$t, $dl, $st] = fields($b);
    $u = db()->prepare('UPDATE task SET title=?, deadline=?, status=? WHERE task_id=? AND booking_id=?'); $u->execute([$t, $dl, $st, (int)($b['task_id'] ?? 0), $bid]);
    out(['ok' => true, 'progress' => progress($bid)]);
}
if ($m === 'DELETE') {
    db()->prepare('DELETE FROM task WHERE task_id=? AND booking_id=?')->execute([(int)($b['task_id'] ?? 0), $bid]);
    out(['ok' => true, 'progress' => progress($bid)]);
}
fail('Method not allowed.', 405);
