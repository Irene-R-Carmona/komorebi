<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface EmailServiceInterface
{
    /**
     * Send verification email
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $verificationUrl
     * @return bool
     */
    public function sendVerificationEmail(string $userEmail, string $userName, string $verificationUrl): bool;

    /**
     * Send generic email
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool;

    /**
     * Send reservation confirmation
     *
     * @param string $userEmail
     * @param string $userName
     * @param array<string, mixed> $reservationData
     * @param string|null $pdfPath
     * @return bool
     */
    /**
     * Backwards-compatible: second argument can be either the userName (string)
     * or the reservationData (array) when called from older code.
     *
     * @param string $userEmail
     * @param mixed $userNameOrReservationData
     * @param array|null $reservationData
     * @param string|null $pdfPath
     * @return bool
     */
    public function sendReservationConfirmation(string $userEmail, mixed $userNameOrReservationData, ?array $reservationData = null, ?string $pdfPath = null): bool;

    /**
     * Send password reset email.
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $resetUrl
     * @return bool
     */
    public function sendPasswordResetEmail(string $userEmail, string $userName, string $resetUrl): bool;

    /**
     * Send waitlist confirmation email with promotion token.
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $token
     * @param array<string, mixed> $waitlistData
     * @return bool
     */
    public function sendWaitlistConfirmation(string $userEmail, string $userName, string $token, array $waitlistData): bool;
}
