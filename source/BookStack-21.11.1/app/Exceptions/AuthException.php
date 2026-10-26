<?php

namespace BookStack\Exceptions;

/**
 * VLEX SCAFFOLD — not an implemented feature.
 * Chờ nội dung file VLEX gốc; chưa suy đoán hành vi.
 * Replace this entire file with VLEX source, then check imports/schema/permissions.
 * This file is not registered in routes, service providers or auth bindings.
 */
class AuthException
{
    public function __construct()
    {
        throw new \LogicException('VLEX scaffold: app/Exceptions/AuthException.php requires original implementation.');
    }
}
