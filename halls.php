<?php
require __DIR__ . '/../bootstrap.php';
$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'GET') {                       // public catalogue + Smart Hall Match (?guests=N)
    $sql = 'SELECT hall_id, name, capacity, price, description, facilities, image_url, status FROM hall';
    $args = [];
    if (isset($_GET['guests'])) {
        $g = (int)$_GET['guests'];
        if ($g < 1) fail('Enter a valid number of guests.');
        $sql .= " WHERE status='Available' AND capacity >= ?";
        $args[] = $g;
    }
    $s = db()->prepare($sql . ' ORDER BY capacity DESC'); $s->execute($args);
    $halls = $s->fetchAll();
    if (isset($g) && !$halls) {
        $max = (int)db()->query("SELECT COALESCE(MAX(capacity),0) FROM hall WHERE status='Available'")->fetchColumn();
        out(['halls' => [], 'message' => "No hall can host $g guests. The largest capacity is $max."]);
    }
    out(['halls' => $halls]);
}
if ($m === 'PUT') {                       // administrator updates hall details
    require_admin(); $b = body();
    $cap = (int)($b['capacity'] ?? 0); $price = (float)($b['price'] ?? -1);
    if ($cap < 1 || $price < 0 || str($b, 'name', 100) === '') fail('Name, capacity and price are required.');
    $status = in_array($b['status'] ?? '', ['Available', 'Maintenance'], true) ? $b['status'] : 'Available';
    db()->prepare('UPDATE hall SET name=?, capacity=?, price=?, status=?, description=?, facilities=?, image_url=? WHERE hall_id=?')
        ->execute([str($b, 'name', 100), $cap, $price, $status, str($b, 'description', 2000), str($b, 'facilities'), str($b, 'image_url'), (int)($b['hall_id'] ?? 0)]);
    out(['ok' => true]);
}
fail('Method not allowed.', 405);
