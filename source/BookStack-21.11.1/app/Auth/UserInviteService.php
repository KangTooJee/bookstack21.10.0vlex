<?php

namespace BookStack\Auth;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Auth/Access/UserInviteService.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Auth\Access\UserInviteService::class, __NAMESPACE__ . '\\UserInviteService');
