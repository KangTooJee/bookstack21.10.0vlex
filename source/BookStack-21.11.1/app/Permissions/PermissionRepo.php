<?php

namespace BookStack\Permissions;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Permissions/PermissionsRepo.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Permissions\PermissionsRepo::class, __NAMESPACE__ . '\\PermissionRepo');
