<?php

namespace BookStack\ViewComponents;

class HierarchyFolder
{
    public function contents(\BookStack\Entities\Models\Book $book): array
    {
        return [
            'books' => $book->childBooks()->scopes('visible')->orderBy('name')->get(),
            'pages' => (new \BookStack\Entities\Tools\BookContents($book))->getTree(true),
            'files' => $book->uploadedFiles()->orderBy('name')->get(),
        ];
    }

    public function breadcrumbs(array $crumbs): array
    {
        return app(\BookStack\View\LibraryMap::class)->expandCrumbs($crumbs);
    }

    public function path(\BookStack\Entities\Models\Book $book): string
    {
        return app(\BookStack\View\LibraryMap::class)->path($book);
    }
}
