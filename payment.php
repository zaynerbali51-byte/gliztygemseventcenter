<?php
require __DIR__ . '/../bootstrap.php';
// POST: simulated card payment. No real gateway; card number and CVV are validated, never stored.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed.', 405);
$b = body();
$id = (int)($b['booking_id'] ?? 0); $email = strtolower(str($b, 'email', 150));
$name = str($b, 'cardholder', 100); $num = preg_replace('/\s+/', '', str($b, 'card_number', 25)); $exp = str($b, 'expiry', 5); $cvv = str($b, 'cvv', 4);

if (mb_strlen($name) < 3) fail('Enter the cardholder name.');
if (!preg_match('/^\d{16}$/', $num) || !luhn($num)) fail('Enter a valid 16-digit card number.');
if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $exp, $x) || new DateTime('20' . $x[2] . '-' . $x[1] . '-01 +1 month') <= new DateTime()) fail('Enter a valid future expiry date as MM/YY.');
if (!preg_match('/^\d{3,4}$/', $cvv)) fail('Enter the 3 or 4 digit CVV.');

$pdo = db(); $pdo->beginTransaction();
$s = $pdo->prepare('SELECT * FROM booking WHERE booking_id=? AND LOWER(email)=? FOR UPDATE'); $s->execute([$id, $email]); $bk = $s->fetch();
if (!$bk) { $pdo->rollBack(); fail('Booking not found.', 404); }
if ($bk['status'] !== 'Pending') { $pdo->rollBack(); fail('This booking has already been processed.', 409); }

$approved = !str_ends_with($num, '0002');           // simulation rule: cards ending 0002 are declined
$reason = $approved ? '' : 'The card was declined.'; $code = null;
if ($approved) {
    for ($try = 0; $try < 3 && !$code; $try++) {
        $c = 'GG-' . strtoupper(bin2hex(random_bytes(3)));
        try {
            $pdo->prepare("UPDATE booking SET status='Confirmed', verification_code=? WHERE booking_id=?")->execute([$c, $id]);
            $code = $c;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            if (str_contains($e->getMessage(), 'uq_confirmed_slot')) { $approved = false; $reason = 'Someone confirmed this date first.'; break; }
        }
    }
    if (!$code && $approved) throw new RuntimeException('Could not generate a unique code.');
}
if (!$approved) $pdo->prepare("UPDATE booking SET status='Declined' WHERE booking_id=?")->execute([$id]);
$pdo->prepare('INSERT INTO payment (booking_id, amount, card_last4, status) VALUES (?,?,?,?)')
    ->execute([$id, $bk['amount'], substr($num, -4), $approved ? 'Approved' : 'Declined']);
$pdo->commit();

out($approved ? ['status' => 'Approved', 'verification_code' => $code] : ['status' => 'Declined', 'message' => $reason . ' Your booking was not confirmed and no money moved.'], $approved ? 200 : 402);
