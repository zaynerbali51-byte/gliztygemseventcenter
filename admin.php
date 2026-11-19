<?php
require __DIR__ . '/../bootstrap.php';
$a = $_GET['action'] ?? '';

if ($a === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body(); usleep(300000);                       // slows down password guessing
    $s = db()->prepare('SELECT admin_id, password_hash, full_name FROM admin WHERE username=?'); $s->execute([str($b, 'username', 50)]); $ad = $s->fetch();
    if (!$ad || !password_verify((string)($b['password'] ?? ''), $ad['password_hash'])) fail('Wrong username or password.', 401);
    session_regenerate_id(true); $_SESSION['admin_id'] = (int)$ad['admin_id'];
    out(['ok' => true, 'name' => $ad['full_name']]);
}
if ($a === 'logout') { $_SESSION = []; session_destroy(); out(['ok' => true]); }

require_admin();
switch ($a) {
    case 'me':    out(['admin_id' => $_SESSION['admin_id']]);
    case 'stats': out(db()->query('SELECT * FROM v_dashboard_stats')->fetch());
    case 'payments':
        out(['payments' => db()->query("SELECT p.payment_id, p.booking_id, p.paid_at, p.amount, p.card_last4, p.status, b.customer_name, b.verification_code
            FROM payment p JOIN booking b ON b.booking_id=p.booking_id ORDER BY p.paid_at DESC")->fetchAll()]);
    case 'customers':
        out(['customers' => db()->query("SELECT customer_name, email, phone, COUNT(*) AS bookings, MAX(created_at) AS last_booking
            FROM booking GROUP BY customer_name, email, phone ORDER BY last_booking DESC")->fetchAll()]);
    default: fail('Unknown action.', 404);
}
