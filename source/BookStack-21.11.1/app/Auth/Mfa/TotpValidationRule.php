<?php

namespace BookStack\Auth\Mfa;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/Mfa/TotpValidationRule.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\Mfa\TotpValidationRule::class, __NAMESPACE__ . '\\TotpValidationRule');
