<?php

namespace BookStack\Permissions;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Role.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Role::class, __NAMESPACE__ . '\\Role');
