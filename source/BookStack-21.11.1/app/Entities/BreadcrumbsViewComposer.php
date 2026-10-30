<?php

namespace BookStack\Entities;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Tools\ShelfContext;
use Illuminate\View\View;

class BreadcrumbsViewComposer
{
    protected $entityContextManager;

    /**
     * BreadcrumbsViewComposer constructor.
     *
     * @param ShelfContext $entityContextManager
     */
    public function __construct(ShelfContext $entityContextManager)
    {
        $this->entityContextManager = $entityContextManager;
    }

    /**
     * Modify data when the view is composed.
     *
     * @param View $view
     */
    public function compose(View $view)
    {
        $view->with('crumbs', app(\BookStack\ViewComponents\HierarchyFolder::class)->breadcrumbs($view->getData()['crumbs']));
    }
}
