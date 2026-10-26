<?php

namespace BookStack\Auth\Mfa;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/Mfa/BackupCodeService.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\Mfa\BackupCodeService::class, __NAMESPACE__ . '\\BackupCodeService');
