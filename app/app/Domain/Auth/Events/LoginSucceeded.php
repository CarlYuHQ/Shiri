<?php

namespace Shiri\Domain\Auth\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a user signed in successfully. For accounts with two-factor authentication it fires once the second factor was verified, not after the password alone.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class LoginSucceeded implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The id of the user who signed in.
     * @param  string  $method  How the user authenticated: 'password', 'ldap', 'oidc', 'token', ... (the first factor, also when 2FA completed the sign-in).
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $method,
    ) {}
}
