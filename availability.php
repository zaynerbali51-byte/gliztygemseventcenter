<?php
require __DIR__ . '/../bootstrap.php';
// GET ?hall_id=1&month=2026-12  -> confirmed (unavailable) dates for the calendar
$hall = (int)($_GET['hall_id'] ?? 0); $month = $_GET['month'] ?? '';
if ($hall < 1 || !preg_match('/^\d{4}-\d{2}$/', $month)) fail('hall_id and month (YYYY-MM) are required.');
$s = db()->prepare("SELECT event_date FROM booking WHERE hall_id=? AND status='Confirmed' AND event_date BETWEEN ? AND LAST_DAY(?)");
$s->execute([$hall, "$month-01", "$month-01"]);
out(['booked' => array_column($s->fetchAll(), 'event_date')]);
