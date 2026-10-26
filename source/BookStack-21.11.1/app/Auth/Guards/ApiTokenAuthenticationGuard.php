<?php

namespace BookStack\Auth\Guards;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Api/ApiTokenGuard.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Api\ApiTokenGuard::class, __NAMESPACE__ . '\\ApiTokenAuthenticationGuard');
