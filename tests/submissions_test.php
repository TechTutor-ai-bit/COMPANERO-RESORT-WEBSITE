<?php
declare(strict_types=1);

if (getenv('APP_ENV') === false) {
    putenv('APP_ENV=development');
}

require_once __DIR__ . '/../includes/submissions.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectInvalid(array $payload, string $message): void
{
    try {
        Submissions::validate($payload);
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

$message = Submissions::validate([
    'type' => 'message',
    'fields' => [
        'name' => 'Guest Name',
        'email' => 'guest@example.org',
        'message' => 'Could you share more information?',
    ],
]);
expect($message['full_name'] === 'Guest Name', 'Contact form name was not mapped.');
expect($message['email'] === 'guest@example.org', 'Contact form email was not mapped.');

$checkIn = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
$checkOut = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
$reservation = Submissions::validate([
    'type' => 'reservation',
    'fields' => [
        'first_name' => 'Guest',
        'last_name' => 'Name',
        'email' => 'guest@example.org',
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guests' => '5+',
        'room' => 'Any available room',
        'special_requests' => 'Late arrival',
    ],
]);
expect($reservation['number_of_guests'] === 5, 'Guest count was not parsed.');
expect($reservation['check_out_date'] > $reservation['check_in_date'], 'Stay date range was not preserved.');

$table = Submissions::validate([
    'type' => 'table',
    'fields' => [
        'name' => 'Guest Name',
        'phone' => '+232 30 555 555',
        'table_date' => date('Y-m-d'),
        'table_time' => '18:30',
        'guests' => '3',
        'occasion' => 'Birthday',
    ],
]);
expect($table['table_time'] === '18:30:00', 'Table time was not normalized.');

expectInvalid([
    'type' => 'message',
    'fields' => ['name' => 'Guest', 'email' => 'bad-email', 'message' => 'Hello'],
], 'Invalid email should be rejected.');
expectInvalid([
    'type' => 'reservation',
    'fields' => [
        'first_name' => 'Guest',
        'last_name' => 'Name',
        'email' => 'guest@example.org',
        'check_in' => $checkOut,
        'check_out' => $checkIn,
    ],
], 'Invalid stay date order should be rejected.');

fwrite(STDOUT, "Submission validation tests passed.\n");
