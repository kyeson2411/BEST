<?php

declare(strict_types=1);

namespace Sibol\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Sibol\Core\Config;
use Sibol\Core\Logger;
use Sibol\Core\Translator;

/**
 * Optional email over SMTP (PHPMailer), set in .env. When MAIL_HOST is empty email is off and Sibol keeps showing
 * links for administrators to copy. The password is never shown or logged; failures are logged without the message.
 */
final class Mailer
{
    /** @var list<array{to:string,subject:string,text:string}>|null messages kept instead of sent, for tests */
    private static ?array $fake = null;

    public static function enabled(): bool
    {
        return self::$fake !== null || (trim((string) Config::get('mail.host', '')) !== '' && filter_var((string) Config::get('mail.from_address', ''), FILTER_VALIDATE_EMAIL) !== false);
    }

    /** Tests: keep messages in memory instead of sending them. */
    public static function fake(): void
    {
        self::$fake = [];
    }

    /** @return list<array{to:string,subject:string,text:string}> */
    public static function sent(): array
    {
        return self::$fake ?? [];
    }

    public static function unfake(): void
    {
        self::$fake = null;
    }

    /**
     * What the integrations page and `check` show: never the password.
     * @return array{enabled:bool,host:string,port:int,encryption:string,username:string,has_password:bool,from:string}
     */
    public static function status(): array
    {
        return [
            'enabled' => self::enabled(),
            'host' => (string) Config::get('mail.host', ''),
            'port' => (int) Config::get('mail.port', 587),
            'encryption' => self::encryption(),
            'username' => (string) Config::get('mail.username', ''),
            'has_password' => (string) Config::get('mail.password', '') !== '',
            'from' => trim((string) Config::get('mail.from_name', Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System')) . ' <' . (string) Config::get('mail.from_address', '') . '>'),
        ];
    }

    /** Sends one plain-text email. True when the mail server accepted it. */
    public static function send(string $to, string $subject, string $text): bool
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || !self::enabled()) {
            return false;
        }
        if (self::$fake !== null) {
            self::$fake[] = ['to' => $to, 'subject' => $subject, 'text' => $text];
            return true;
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) Config::get('mail.host');
            $mail->Port = (int) Config::get('mail.port', 587);
            if (self::encryption() === 'none') {
                // Only for a test server on this computer; real mail servers need tls or ssl.
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = self::encryption() === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            $username = (string) Config::get('mail.username', '');
            $mail->SMTPAuth = $username !== '';
            $mail->Username = $username;
            $mail->Password = (string) Config::get('mail.password', '');
            $mail->SMTPDebug = 0;
            $mail->Timeout = 20;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->XMailer = ' ';
            $mail->setFrom((string) Config::get('mail.from_address'), (string) Config::get('mail.from_name', Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System')));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(false);
            $mail->Body = $text;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            Logger::warning('Email not sent', ['error' => self::clean($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage())]);
            return false;
        }
    }

    /**
     * An invitation or password link for an account, in the person's own language. The link is still shown to the
     * administrator to copy, in case the email does not arrive.
     */
    public static function accountLink(array $person, string $purpose, string $url, string $expires, string $school): bool
    {
        $email = (string) ($person['email'] ?? '');
        if ($email === '' || !self::enabled()) {
            return false;
        }
        return Translator::with((string) ($person['language'] ?? ''), static function () use ($person, $purpose, $url, $expires, $school, $email): bool {
            $name = user_name($person);
            if ($purpose === 'invitation') {
                $subject = t('Your B.E.S.T. Learning and Interactive Study System account at :school', ['school' => $school]);
                $text = t('Hello :name,', ['name' => $name]) . "\n\n"
                    . t(':school uses B.E.S.T. Learning and Interactive Study System for learning. Choose a password to start:', ['school' => $school]) . "\n" . $url . "\n\n"
                    . ($person['username'] ? t('Your username is :username.', ['username' => $person['username']]) . ' ' : '')
                    . t('The link works once and expires in :time.', ['time' => $expires]) . "\n\n"
                    . t('If you were not expecting this email, you can ignore it.');
            } else {
                $subject = t('Choose a new B.E.S.T. Learning and Interactive Study System password');
                $text = t('Hello :name,', ['name' => $name]) . "\n\n"
                    . t('Your school created a link for you to choose a new B.E.S.T. Learning and Interactive Study System password:') . "\n" . $url . "\n\n"
                    . t('The link works once and expires in :time. Your current password keeps working until you use it.', ['time' => $expires]) . "\n\n"
                    . t('If you did not ask for this, tell your school\'s administrator.');
            }
            return self::send($email, $subject, $text);
        });
    }

    /** tls (STARTTLS), ssl, or none for a local test server only. */
    private static function encryption(): string
    {
        $value = strtolower((string) Config::get('mail.encryption', 'tls'));
        return in_array($value, ['ssl', 'none'], true) ? $value : 'tls';
    }

    /** Removes the password from an error message, in case a server ever echoes it. */
    private static function clean(string $message): string
    {
        $password = (string) Config::get('mail.password', '');
        return $password !== '' ? str_replace($password, '[hidden]', $message) : $message;
    }
}
