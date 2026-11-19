<?php
require __DIR__ . '/../bootstrap.php';
$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'POST') {                      // customer creates a Pending booking
    $b = body();
    $d = ['name' => str($b, 'name', 120), 'email' => strtolower(str($b, 'email', 150)), 'phone' => str($b, 'phone', 25),
          'type' => str($b, 'event_type', 60), 'hall' => (int)($b['hall_id'] ?? 0), 'date' => str($b, 'event_date', 10),
          'guests' => (int)($b['guests'] ?? 0), 'info' => str($b, 'additional_info', 2000)];
    if (mb_strlen($d['name']) < 3) fail('Enter your full name.');
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
    if (!preg_match('/^[+\d][\d\s-]{6,17}$/', $d['phone'])) fail('Enter a valid phone number.');
    if ($d['type'] === '') fail('Choose an event type.');
    $dt = DateTime::createFromFormat('Y-m-d', $d['date']);
    if (!$dt || $dt->format('Y-m-d') !== $d['date'] || $d['date'] < date('Y-m-d')) fail('Choose an event date that is today or later.');
    if ($d['guests'] < 1) fail('Enter the number of expected guests.');
    $h = db()->prepare('SELECT * FROM hall WHERE hall_id=?'); $h->execute([$d['hall']]); $hall = $h->fetch();
    if (!$hall) fail('Hall not found.', 404);
    if ($hall['status'] !== 'Available') fail('That hall is not available for booking right now.');
    if ($d['guests'] > (int)$hall['capacity']) fail("{$hall['name']} holds up to {$hall['capacity']} guests.");
    if (date_taken($d['hall'], $d['date'])) fail("{$hall['name']} is already confirmed on {$d['date']}. Pick another date.", 409);
    db()->prepare('INSERT INTO booking (customer_name,email,phone,event_type,hall_id,event_date,guests,additional_info,amount) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$d['name'], $d['email'], $d['phone'], $d['type'], $d['hall'], $d['date'], $d['guests'], $d['info'], $hall['price']]);
    out(['booking_id' => (int)db()->lastInsertId(), 'amount' => (float)$hall['price']], 201);
}

if ($m === 'GET' && isset($_GET['code'])) {   // customer verifies a booking with the code
    $s = db()->prepare("SELECT b.*, h.name AS hall_name FROM booking b JOIN hall h ON h.hall_id=b.hall_id WHERE b.verification_code=?");
    $s->execute([strtoupper(trim($_GET['code']))]); $bk = $s->fetch();
    if (!$bk) fail('No booking found for that code.', 404);
    $p = db()->prepare('SELECT total_tasks, completed_tasks, progress_percent FROM v_booking_progress WHERE booking_id=?');
    $p->execute([$bk['booking_id']]);
    out(['booking' => $bk, 'progress' => $p->fetch()]);
}

if ($m === 'GET') {                       // administrator list with search and filter
    require_admin();
    $sql = 'SELECT b.*, h.name AS hall_name FROM booking b JOIN hall h ON h.hall_id=b.hall_id WHERE 1=1'; $a = [];
    if (!empty($_GET['status'])) { $sql .= ' AND b.status=?'; $a[] = $_GET['status']; }
    if (!empty($_GET['q'])) { $sql .= ' AND (b.customer_name LIKE ? OR b.email LIKE ? OR b.verification_code LIKE ?)'; $l = '%' . $_GET['q'] . '%'; array_push($a, $l, $l, $l); }
    if (!empty($_GET['date'])) { $sql .= ' AND b.event_date=?'; $a[] = $_GET['date']; }
    $s = db()->prepare($sql . ' ORDER BY b.created_at DESC'); $s->execute($a);
    out(['bookings' => $s->fetchAll()]);
}
fail('Method not allowed.', 405);
