<?php

namespace BookStack\Auth\Guards;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/Guards/LdapSessionGuard.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\Guards\LdapSessionGuard::class, __NAMESPACE__ . '\\LdapSessionGuard');
