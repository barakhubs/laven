<?php

namespace App\Services;

/**
 * Why a member's SMS sign-in couldn't go ahead. `errorCode` is what the
 * mobile app reads (e.g. NOT_A_MEMBER, OTP_WAIT); the message is for people.
 */
class MemberOtpLoginException extends \RuntimeException
{
    public function __construct(string $message, public string $errorCode, public int $status = 422, public ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
