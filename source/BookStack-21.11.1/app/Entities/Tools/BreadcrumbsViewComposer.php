<?php

namespace BookStack\Entities\Tools;

/**
 * VLEX compatibility path. Uses the existing BookStack implementation.
 * Source: app/Entities/BreadcrumbsViewComposer.php
 * Replace this entire file when the real VLEX implementation is available.
 * Runtime bindings and existing BookStack classes are unchanged.
 */
class_alias(\BookStack\Entities\BreadcrumbsViewComposer::class, __NAMESPACE__ . '\\BreadcrumbsViewComposer');
