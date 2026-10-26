<?php

namespace BookStack\Entities\Tools;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Entities/Tools/SiblingFetcher.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Entities\Tools\SiblingFetcher::class, __NAMESPACE__ . '\\SiblingFinder');
