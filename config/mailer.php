<?php
/**
 * Mailer Factory
 * Returns a pre-configured PHPMailer instance using SMTP settings from .env
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// Load environment variables if not already loaded
include_once __DIR__ . '/env.php';

/**
 * Creates and returns a configured PHPMailer instance.
 *
 * @throws Exception if configuration is missing
 * @return PHPMailer
 */
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true); // true = exceptions enabled

    // ── Server Settings ──────────────────────────────────────
    $mail->isSMTP();
    $mail->Host       = getenv('MAIL_HOST')     ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME') ?: '';
    $mail->Password   = getenv('MAIL_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);

    // Bypass SSL verify on localhost (safe for dev only)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ]
    ];

    // ── Sender Identity ──────────────────────────────────────
    $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: (getenv('MAIL_USERNAME') ?: 'noreply@starosa1.gov.ph');
    $fromName    = getenv('MAIL_FROM_NAME')    ?: 'Barangay Sta. Rosa 1';
    $mail->setFrom($fromAddress, $fromName);

    // ── Character Set ─────────────────────────────────────────
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    return $mail;
}
