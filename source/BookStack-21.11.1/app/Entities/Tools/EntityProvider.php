<?php

namespace BookStack\Entities\Tools;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Entities/EntityProvider.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Entities\EntityProvider::class, __NAMESPACE__ . '\\EntityProvider');
