<?php

namespace BookStack\View;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryMap
{
    public function matchingBookIds(string $term): array
    {
        $pattern = '%' . addcslashes($term, '\\%_') . '%';
        $ids = Book::visible()->where('name', 'like', $pattern)->pluck('id')->all();
        $shelves = Bookshelf::visible()->where('name', 'like', $pattern)->pluck('id');
        $roots = Book::visible()->whereHas('shelves', function ($query) use ($shelves) {
            $query->whereIn('bookshelves.id', $shelves);
        })->pluck('id')->all();
        $ids = array_values(array_unique(array_merge($ids, $roots)));
        $frontier = $ids;
        while ($frontier) {
            $next = Book::visible()->whereIn('parent_book_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($next, $ids));
            $ids = array_merge($ids, $frontier);
        }
        return $ids;
    }

    public function ancestors(Book $book): array
    {
        $ancestors = [];
        $seen = [$book->id => true];
        while ($book->parent_book_id) {
            $book = Book::findOrFail($book->parent_book_id);
            abort_if(isset($seen[$book->id]), 409, 'Cây book có vòng lặp.');
            $seen[$book->id] = true;
            array_unshift($ancestors, $book);
        }
        $shelf = $book->shelves()->scopes('visible')->first();
        if ($shelf) array_unshift($ancestors, $shelf);
        return $ancestors;
    }

    public function expandCrumbs(array $crumbs): array
    {
        if (($crumbs[0] ?? null) instanceof Book) {
            return array_merge($this->ancestors($crumbs[0]), $crumbs);
        }
        return $crumbs;
    }

    public function path(Book $book): string
    {
        $ancestors = array_filter($this->ancestors($book), function ($entity) { return userCan('view', $entity); });
        $ancestors[] = $book;
        return implode(' / ', array_map(function ($entity) { return $entity->name; }, $ancestors));
    }

    public function descendantIds(Book $book, bool $lock = false): array
    {
        $ids = [$book->id];
        $frontier = [$book->id];
        while ($frontier) {
            $query = Book::withTrashed()->whereIn('parent_book_id', $frontier);
            if ($lock) $query->lockForUpdate();
            $next = $query->pluck('id')->all();
            abort_if(count(array_intersect($ids, $next)) > 0, 409, 'Cây book có vòng lặp.');
            $ids = array_merge($ids, $next);
            $frontier = $next;
        }
        return $ids;
    }

    public function place(Book $book, ?Book $parent, ?Bookshelf $shelf, bool $new = false): void
    {
        if (($parent === null) === ($shelf === null)) {
            throw ValidationException::withMessages(['destination' => 'Chọn đúng một book hoặc shelf đích.']);
        }
        DB::transaction(function () use ($book, $parent, $shelf, $new) {
            DB::table('vlex_book_hierarchy_lock')->where('id', 1)->lockForUpdate()->first();
            $current = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();
            if ($new) {
                abort_unless((int) $current->created_by === (int) user()->id, 403);
            } else {
                abort_unless(userCan('book-update', $current), 403);
                if ($current->parent_book_id) {
                    $oldParent = Book::findOrFail($current->parent_book_id);
                    abort_unless(userCan('view', $oldParent) && userCan('book-update', $oldParent), 403);
                }
                foreach ($current->shelves()->get() as $oldShelf) {
                    abort_unless(userCan('view', $oldShelf) && userCan('bookshelf-update', $oldShelf), 403);
                }
            }
            $descendants = $this->descendantIds($current, true);
            if ($parent) {
                $parent = Book::visible()->whereKey($parent->id)->lockForUpdate()->firstOrFail();
                abort_unless(userCan('book-update', $parent), 403);
                if (in_array($parent->id, $descendants)) {
                    throw ValidationException::withMessages(['destination' => 'Không chuyển book vào chính nó hoặc book con của nó.']);
                }
            } else {
                $shelf = Bookshelf::visible()->findOrFail($shelf->id);
                abort_unless(userCan('bookshelf-update', $shelf), 403);
            }
            // Nested books have no direct shelf membership. Clear every previous link.
            DB::table('bookshelves_books')->whereIn('book_id', $descendants)->delete();
            $current->parent_book_id = $parent ? $parent->id : null;
            $current->save();
            if ($shelf) {
                $order = ((int) $shelf->books()->max('order')) + 1;
                $shelf->books()->attach($current->id, ['order' => $order]);
            }
            $book->parent_book_id = $current->parent_book_id;
            $book->unsetRelation('shelves');
            $book->unsetRelation('parentBook');
        });
    }
}
