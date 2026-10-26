<?php

namespace BookStack\Auth;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/UserTokenService.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\UserTokenService::class, __NAMESPACE__ . '\\UserTokenService');
