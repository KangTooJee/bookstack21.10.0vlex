<?php

namespace BookStack\Auth;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/EmailConfirmationService.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\EmailConfirmationService::class, __NAMESPACE__ . '\\EmailConfirmationService');
