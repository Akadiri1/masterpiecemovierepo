<?php
/**
 * Sending email from the site through an SMTP account (Gmail by default).
 *
 * Set these on the server (Render > Environment):
 *   MAIL_FROM       the sending address, e.g. zenmovies@gmail.com
 *   EMAIL_PASSWORD  its password. For Gmail this must be an App Password
 *                   (Google Account > Security > 2-Step Verification > App passwords).
 * Optional:
 *   MAIL_FROM_NAME  sender name (default "ZEN")
 *   MAIL_HOST       SMTP server (default smtp.gmail.com)
 *   MAIL_PORT       587 (default) or 465
 *   MAIL_USERNAME   login, when it differs from MAIL_FROM
 *   MAIL_SECURE     tls, ssl or none (default: ssl on port 465, otherwise tls)
 */

function mailConfigured(): bool
{
    return filter_var(getenv('MAIL_FROM'), FILTER_VALIDATE_EMAIL) && (string) getenv('EMAIL_PASSWORD') !== '';
}

/** Sends one email. On failure returns false and puts the reason in $error. */
function sendSiteMail(string $to, string $subject, string $html, string $text, ?string &$error = null): bool
{
    if (!mailConfigured()) {
        $error = 'Email is not set up on the server (MAIL_FROM and EMAIL_PASSWORD).';
        return false;
    }
    require_once APP_PATH . '/phpm/PHPMailerAutoload.php';

    $port = (int) (getenv('MAIL_PORT') ?: 587);
    $secure = getenv('MAIL_SECURE') ?: ($port === 465 ? 'ssl' : 'tls');

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->Port = $port;
        if ($secure === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $secure;
        }
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USERNAME') ?: getenv('MAIL_FROM');
        $mail->Password = getenv('EMAIL_PASSWORD');
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(getenv('MAIL_FROM'), getenv('MAIL_FROM_NAME') ?: 'ZEN');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        error_log('sendSiteMail to ' . $to . ': ' . $error);
        return false;
    }
}
