<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/rate-limiter.php';

final class Submissions
{
    public static function create(array $payload): string
    {
        $submission = self::validate($payload);
        $type = $submission['type'];

        $identity = RateLimiter::clientIdentity();
        if (!RateLimiter::consume('public_form', $identity, 10, 3600)) {
            throw new LengthException('Too many requests. Please try again later.');
        }

        $id = self::uuid();
        $submission['id'] = $id;

        $columns = [
            'id', 'type', 'full_name', 'email', 'phone', 'subject', 'message',
            'check_in_date', 'check_out_date', 'number_of_guests', 'number_of_rooms',
            'special_requests', 'table_date', 'table_time', 'table_guests', 'notification_status',
        ];
        $submission['notification_status'] = 'pending';
        $values = array_map(static fn(string $column) => $submission[$column] ?? null, $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO submissions (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($values);

        $notificationStatus = self::notifyStaff($submission);
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE submissions SET notification_status = ? WHERE id = ?'
            );
            $stmt->execute([$notificationStatus, $id]);
        } catch (Throwable $error) {
            error_log('Submission notification status update failed: ' . $error->getMessage());
        }
        return $id;
    }

    public static function validate(array $payload): array
    {
        $type = $payload['type'] ?? null;
        $fields = $payload['fields'] ?? null;
        if (!in_array($type, ['message', 'reservation', 'table'], true) || !is_array($fields)) {
            throw new InvalidArgumentException('Invalid request type.');
        }

        $honeypot = $fields['website'] ?? '';
        if (!is_string($honeypot)) {
            throw new InvalidArgumentException('Invalid field value.');
        }
        if (trim($honeypot) !== '') {
            throw new DomainException('The request could not be accepted.');
        }

        $submission = match ($type) {
            'message' => self::message($fields),
            'reservation' => self::reservation($fields),
            'table' => self::table($fields),
        };
        $submission['type'] = $type;
        return $submission;
    }

    private static function message(array $fields): array
    {
        return self::emptyRecord(
            self::requiredText($fields, 'name', 120),
            self::requiredEmail($fields),
            self::optionalPhone($fields),
            null,
            self::requiredText($fields, 'message', 5000)
        );
    }

    private static function reservation(array $fields): array
    {
        $firstName = self::requiredText($fields, 'first_name', 80);
        $lastName = self::requiredText($fields, 'last_name', 80);
        $checkIn = self::requiredDate($fields, 'check_in', true);
        $checkOut = self::requiredDate($fields, 'check_out', true);
        if ($checkOut <= $checkIn) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }

        $record = self::emptyRecord(
            $firstName . ' ' . $lastName,
            self::requiredEmail($fields),
            self::optionalPhone($fields),
            self::optionalText($fields, 'room', 120),
            null
        );
        $record['check_in_date'] = $checkIn;
        $record['check_out_date'] = $checkOut;
        $record['number_of_guests'] = self::guestCount($fields['guests'] ?? '2');
        $record['special_requests'] = self::optionalText($fields, 'special_requests', 3000);
        return $record;
    }

    private static function table(array $fields): array
    {
        $record = self::emptyRecord(
            self::requiredText($fields, 'name', 120),
            self::optionalEmail($fields),
            self::requiredPhone($fields),
            self::optionalText($fields, 'occasion', 120),
            self::optionalText($fields, 'notes', 3000)
        );
        $record['table_date'] = self::requiredDate($fields, 'table_date', true);
        $record['table_time'] = self::requiredTime($fields, 'table_time');
        $record['table_guests'] = self::guestCount($fields['guests'] ?? '2');
        return $record;
    }

    private static function emptyRecord(?string $name, ?string $email, ?string $phone, ?string $subject, ?string $message): array
    {
        return [
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'check_in_date' => null,
            'check_out_date' => null,
            'number_of_guests' => null,
            'number_of_rooms' => null,
            'special_requests' => null,
            'table_date' => null,
            'table_time' => null,
            'table_guests' => null,
        ];
    }

    private static function requiredText(array $fields, string $key, int $maxBytes): string
    {
        $value = self::field($fields, $key);
        if ($value === '' || strlen($value) > $maxBytes || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('A required field is missing or invalid.');
        }
        return $value;
    }

    private static function optionalText(array $fields, string $key, int $maxBytes): ?string
    {
        $value = self::field($fields, $key, false);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $maxBytes || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('A field is too long or invalid.');
        }
        return $value;
    }

    private static function field(array $fields, string $key, bool $required = true): string
    {
        $value = $fields[$key] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid field value.');
        }
        $value = trim($value);
        if ($required && $value === '') {
            throw new InvalidArgumentException('A required field is missing.');
        }
        return $value;
    }

    private static function requiredEmail(array $fields): string
    {
        $email = self::field($fields, 'email');
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        return $email;
    }

    private static function optionalEmail(array $fields): ?string
    {
        $email = self::field($fields, 'email', false);
        if ($email === '') {
            return null;
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        return $email;
    }

    private static function optionalPhone(array $fields): ?string
    {
        $phone = self::field($fields, 'phone', false);
        if ($phone === '') {
            return null;
        }
        if (strlen($phone) > 32 || !preg_match('/^[0-9+().\-\s]{7,32}$/', $phone)) {
            throw new InvalidArgumentException('Enter a valid phone number.');
        }
        return $phone;
    }

    private static function requiredPhone(array $fields): string
    {
        $phone = self::optionalPhone($fields);
        if ($phone === null) {
            throw new InvalidArgumentException('A phone number is required.');
        }
        return $phone;
    }

    private static function requiredDate(array $fields, string $key, bool $allowToday): string
    {
        $date = self::field($fields, $key);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || $parsed->format('Y-m-d') !== $date
            || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date < date('Y-m-d')
            || (!$allowToday && $date === date('Y-m-d'))
        ) {
            throw new InvalidArgumentException('Enter a valid future date.');
        }
        return $date;
    }

    private static function requiredTime(array $fields, string $key): string
    {
        $time = self::field($fields, $key);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException('Enter a valid time.');
        }
        return $time . ':00';
    }

    private static function guestCount($value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Select a valid number of guests.');
        }
        $value = (string) $value;
        if (!preg_match('/^\d{1,2}\+?$/', $value)) {
            throw new InvalidArgumentException('Select a valid number of guests.');
        }
        $count = (int) $value;
        if ($count < 1 || $count > 30) {
            throw new InvalidArgumentException('Select a valid number of guests.');
        }
        return $count;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function notifyStaff(array $submission): string
    {
        if (
            MAIL_TO === ''
            || MAIL_FROM === ''
            || filter_var(MAIL_TO, FILTER_VALIDATE_EMAIL) === false
            || filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL) === false
        ) {
            return 'not_configured';
        }
        if (!function_exists('mail')) {
            error_log('PHP mail transport is unavailable for reference ' . $submission['id']);
            return 'failed';
        }

        $subject = 'New ' . $submission['type'] . ' request (' . $submission['id'] . ')';
        $lines = [
            'A new guest request was saved in the Companero Resort admin inbox.',
            'Reference: ' . $submission['id'],
            'Type: ' . $submission['type'],
            'Name: ' . ($submission['full_name'] ?? ''),
            'Email: ' . ($submission['email'] ?? ''),
            'Phone: ' . ($submission['phone'] ?? ''),
            'Details: ' . ($submission['subject'] ?? ''),
            'Message: ' . ($submission['message'] ?? $submission['special_requests'] ?? ''),
            'Check-in: ' . ($submission['check_in_date'] ?? ''),
            'Check-out: ' . ($submission['check_out_date'] ?? ''),
            'Table date/time: ' . ($submission['table_date'] ?? '') . ' ' . ($submission['table_time'] ?? ''),
        ];
        $headers = [
            'From: ' . MAIL_FROM,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        try {
            $accepted = mail(MAIL_TO, $subject, implode("\n", $lines), implode("\r\n", $headers));
        } catch (Throwable $error) {
            error_log('Submission notification failed: ' . $error->getMessage());
            return 'failed';
        }
        if (!$accepted) {
            error_log('Submission notification delivery failed for reference ' . $submission['id']);
            return 'failed';
        }
        return 'accepted';
    }
}
