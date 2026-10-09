<?php
/**
 * CITOS contact form handler.
 *
 * The only server-side file in the site. contact.html posts here; this script
 * validates the message, emails it to the research team over SMTP and, when
 * enabled, sends the visitor a confirmation. It then redirects back to
 * contact.html with a fragment (#sent, #send-invalid, #send-error) that CSS
 * uses to show the result, so the page itself needs no JavaScript.
 *
 * Settings come from environment variables (how Cloud Run and Docker pass
 * them) or from a .env file (see .env.example). Environment variables win.
 * The .env file is looked for one folder ABOVE the website first, so it can
 * live outside the public web root, then next to this script (where
 * .htaccess blocks direct downloads).
 *
 * Requires PHP 7.4+ with the openssl extension. No Composer packages.
 */

declare(strict_types=1);

const RATE_LIMIT = 5;          // submissions per IP …
const RATE_WINDOW = 600;       // … every 10 minutes
const MESSAGE_MAX = 5000;
const SITE_NAME = 'CITOS';
const PROJECT_ID = 'R26-DS-013';
const TAGLINE = 'Comprehensive Intelligent Transport Observation System';
const INSTITUTION = 'Sri Lanka Institute of Information Technology (SLIIT)';

function back(string $result): void
{
    header('Location: contact.html#' . $result, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: contact.html', true, 303);
    exit;
}

/* ───────────────────────────── Settings ───────────────────────────── */

const SETTINGS = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_PASS', 'MAIL_FROM',
    'CONTACT_RECIPIENTS', 'CONTACT_SEND_AUTOREPLY'];

/** Real environment variables override the .env file. */
function load_settings(): array
{
    $env = load_env_file();
    foreach (SETTINGS as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            $env[$key] = $value;
        }
    }
    return $env;
}

function load_env_file(): array
{
    foreach ([dirname(__DIR__) . '/.env', __DIR__ . '/.env'] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $env = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $env[$key] = $value;
        }
        return $env;
    }
    return [];
}

function is_true(string $value): bool
{
    return in_array(strtolower($value), ['true', '1', 'yes'], true);
}

$env = load_settings();
$smtpHost = $env['SMTP_HOST'] ?? '';
$smtpPort = (int) (($env['SMTP_PORT'] ?? '') ?: 465);
$smtpUser = $env['SMTP_USER'] ?? '';
$smtpPass = $env['SMTP_PASS'] ?? '';
// Port 465 uses implicit TLS; 587/25 upgrade with STARTTLS.
$smtpSecure = ($env['SMTP_SECURE'] ?? '') !== '' ? is_true($env['SMTP_SECURE']) : $smtpPort === 465;
$mailFrom = ($env['MAIL_FROM'] ?? '') ?: SITE_NAME . " Research <{$smtpUser}>";
$recipients = array_values(array_filter(array_map('trim', explode(',',
    ($env['CONTACT_RECIPIENTS'] ?? '') ?: 'IT2207184@my.sliit.lk,saifulis.4965@gmail.com'))));
$autoReply = is_true(($env['CONTACT_SEND_AUTOREPLY'] ?? '') ?: 'true');

if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || !$recipients) {
    error_log('[contact] email is not configured: check SMTP_HOST, SMTP_USER, SMTP_PASS in .env');
    back('send-error');
}

/* ───────────────────────────── Input ───────────────────────────── */

/** Character length that works without the mbstring extension. */
function len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

function field(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

$name = field('name');
$email = strtolower(field('email'));
$contactNo = field('contactNo');
$message = str_replace("\r\n", "\n", field('message'));

// Honeypot: hidden from people, filled in by naive bots. Pretend success.
if (field('website') !== '') {
    back('sent');
}

$valid = len($name) >= 2 && len($name) <= 100
    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254
    && preg_match('/^\+?[0-9\s\-()]{7,20}$/', $contactNo) === 1
    && len($message) >= 10 && len($message) <= MESSAGE_MAX;
if (!$valid) {
    back('send-invalid');
}

/* ───────────────────────────── Rate limit ───────────────────────────── */

$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$bucket = sys_get_temp_dir() . '/citos-contact-' . sha1($ip) . '.json';
$now = time();
$hits = is_readable($bucket) ? (json_decode((string) file_get_contents($bucket), true) ?: []) : [];
$hits = array_values(array_filter($hits, static fn ($t) => $now - (int) $t < RATE_WINDOW));
if (count($hits) >= RATE_LIMIT) {
    back('send-limit');
}
$hits[] = $now;
@file_put_contents($bucket, json_encode($hits), LOCK_EX);

/* ───────────────────────────── Email content ───────────────────────────── */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function one_line(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value));
}

function layout(string $title, string $body): string
{
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f1f5f9;font-family:\'Segoe UI\',Helvetica,Arial,sans-serif;color:#0f172a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">'
        . '<tr><td style="background:linear-gradient(120deg,#4f46e5,#7c3aed,#0d9488);background-color:#4f46e5;padding:24px 28px;">'
        . '<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#e0e7ff;">' . h(SITE_NAME) . ' · ' . h(PROJECT_ID) . '</p>'
        . '<h1 style="margin:6px 0 0;font-size:20px;color:#ffffff;">' . h($title) . '</h1></td></tr>'
        . '<tr><td style="padding:28px;">' . $body . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">'
        . h(TAGLINE) . ' — ' . h(INSTITUTION) . '</td></tr></table></td></tr></table></body></html>';
}

function row(string $label, string $value): string
{
    return '<tr><td style="padding:10px 0;width:130px;vertical-align:top;font-size:13px;font-weight:600;color:#64748b;">'
        . h($label) . '</td><td style="padding:10px 0;font-size:14px;color:#0f172a;">' . $value . '</td></tr>';
}

$when = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('j M Y, H:i');
$messageHtml = nl2br(h($message), false);

$notification = [
    'subject' => '[' . SITE_NAME . '] New message from ' . one_line($name),
    'html' => layout('New contact form submission',
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid #e2e8f0;margin-bottom:20px;">'
        . row('Name', h($name))
        . row('Email', '<a href="mailto:' . h($email) . '" style="color:#4f46e5;">' . h($email) . '</a>')
        . row('Contact no.', '<a href="tel:' . preg_replace('/[^\d+]/', '', $contactNo) . '" style="color:#4f46e5;">' . h($contactNo) . '</a>')
        . row('Received', h($when) . ' (Colombo)')
        . '</table><p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#64748b;">Message</p>'
        . '<div style="padding:16px;border-radius:12px;background:#eef2ff;font-size:14px;line-height:1.6;">' . $messageHtml . '</div>'
        . '<p style="margin:20px 0 0;font-size:12px;color:#94a3b8;">Reply directly to this email to respond to ' . h($name) . '.</p>'),
    'text' => implode("\n", [
        'New contact form submission', '',
        "Name:        {$name}", "Email:       {$email}", "Contact no.: {$contactNo}",
        "Received:    {$when} (Colombo)", "IP:          {$ip}", '', 'Message:', $message,
    ]),
];

$first = explode(' ', $name)[0];
$confirmation = [
    'subject' => 'We received your message — ' . SITE_NAME,
    'html' => layout('Thanks for reaching out',
        '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Hi ' . h($first) . ',</p>'
        . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Thank you for contacting the ' . h(SITE_NAME)
        . ' research team. We\'ve received your message and will get back to you as soon as we can.</p>'
        . '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#64748b;">Your message</p>'
        . '<div style="padding:16px;border-radius:12px;background:#f1f5f9;font-size:14px;line-height:1.6;color:#334155;">' . $messageHtml . '</div>'
        . '<p style="margin:20px 0 0;font-size:15px;line-height:1.6;">Best regards,<br>The ' . h(SITE_NAME) . ' team</p>'),
    'text' => implode("\n", [
        "Hi {$name},", '',
        'Thank you for contacting the ' . SITE_NAME . " research team. We've received your message and will get back to you as soon as we can.",
        '', 'Your message:', $message, '', 'Best regards,', 'The ' . SITE_NAME . ' team',
    ]),
];

/* ───────────────────────────── SMTP ───────────────────────────── */

/** Minimal SMTP client: implicit TLS (465) or STARTTLS (587), AUTH LOGIN, one message per call. */
final class Smtp
{
    /** @var resource */
    private $socket;

    public function __construct(string $host, int $port, bool $secure, string $user, string $pass)
    {
        $target = ($secure ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, 15);
        if (!$socket) {
            throw new RuntimeException("connect {$target} failed: {$errstr}");
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 20);
        $this->expect(220);
        $this->command('EHLO ' . (gethostname() ?: 'localhost'), 250);
        if (!$secure) {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $this->command('EHLO ' . (gethostname() ?: 'localhost'), 250);
        }
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($user), 334);
        $this->command(base64_encode($pass), 235);
    }

    public function send(string $from, array $to, string $subject, string $html, string $text, ?string $replyTo = null): void
    {
        $fromAddress = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
        $this->command("MAIL FROM:<{$fromAddress}>", 250);
        foreach ($to as $address) {
            $this->command("RCPT TO:<{$address}>", [250, 251]);
        }
        $this->command('DATA', 354);

        $boundary = 'citos-' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeAddress($from),
            'To: ' . implode(', ', $to),
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($fromAddress, '@'), 1) . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . self::encodeAddress($replyTo);
        }
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$boundary}--\r\n";

        // Base64 bodies never start a line with ".", so no dot-stuffing is needed.
        fwrite($this->socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . ".\r\n");
        $this->expect(250);
    }

    public function close(): void
    {
        @fwrite($this->socket, "QUIT\r\n");
        @fclose($this->socket);
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /** Encodes the display name of "Name <addr>" and strips anything that could add a header. */
    private static function encodeAddress(string $value): string
    {
        $value = one_line($value);
        if (preg_match('/^"?([^"<]*)"?\s*<([^>]+)>$/', $value, $m)) {
            return self::encodeHeader(trim($m[1])) . ' <' . trim($m[2]) . '>';
        }
        return $value;
    }

    /** @param int|int[] $codes */
    private function command(string $line, $codes): void
    {
        fwrite($this->socket, $line . "\r\n");
        $this->expect($codes);
    }

    /** @param int|int[] $codes */
    private function expect($codes): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $codes, true)) {
            throw new RuntimeException('SMTP said: ' . trim($response));
        }
    }
}

/* ───────────────────────────── Send ───────────────────────────── */

try {
    $smtp = new Smtp($smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass);
    $smtp->send($mailFrom, $recipients, $notification['subject'], $notification['html'], $notification['text'],
        '"' . str_replace('"', '', one_line($name)) . "\" <{$email}>");

    // The confirmation is best-effort: a bad visitor address must not fail the request.
    if ($autoReply) {
        try {
            $smtp->send($mailFrom, [$email], $confirmation['subject'], $confirmation['html'], $confirmation['text']);
        } catch (Throwable $e) {
            error_log('[contact] auto-reply failed: ' . $e->getMessage());
        }
    }
    $smtp->close();
} catch (Throwable $e) {
    error_log('[contact] failed to send: ' . $e->getMessage());
    back('send-error');
}

back('sent');
