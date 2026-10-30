<?php

namespace BookStack\Http\Controllers;

use Activity;
use BookStack\Actions\ActivityType;
use BookStack\Actions\View;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\PermissionsUpdater;
use BookStack\Entities\Tools\ShelfContext;
use BookStack\Exceptions\ImageUploadException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookController extends Controller
{
    public function createChild(string $bookSlug)
    {
        $this->checkPermission('book-create-all');
        $parentBook = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-update', $parentBook);
        $this->setPageTitle('Tạo book con');
        return view('books.create-child', compact('parentBook'));
    }

    public function storeChild(Request $request, string $bookSlug)
    {
        $this->checkPermission('book-create-all');
        $parent = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-update', $parent);
        $request->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:1000',
            'image' => array_merge(['nullable'], $this->getImageValidationRules()),
        ]);
        $book = \Illuminate\Support\Facades\DB::transaction(function () use ($parent, $request) {
            $input = $request->all();
            $input['description'] = $input['description'] ?? '';
            $book = $this->bookRepo->create($input);
            app(\BookStack\View\LibraryMap::class)->place($book, $parent, null, true);
            $this->bookRepo->updateCoverImage($book, $request->file('image'));
            return $book;
        });
        return redirect($book->getUrl());
    }

    public function showMoveFolder(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-update', $book);
        $hierarchy = app(\BookStack\View\LibraryMap::class);
        $excluded = $hierarchy->descendantIds($book);
        $books = \BookStack\Entities\Models\Book::visible()->whereNotIn('id', $excluded)->get()
            ->filter(function ($target) { return userCan('book-update', $target); });
        $shelves = Bookshelf::visible()->get()->filter(function ($target) { return userCan('bookshelf-update', $target); });
        $this->setPageTitle('Chuyển book');
        return view('books.move-folder', compact('book', 'books', 'shelves', 'hierarchy'));
    }

    public function moveFolder(Request $request, string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-update', $book);
        $request->validate(['destination' => ['required', 'string', 'regex:/^(book|shelf):[1-9][0-9]*$/']]);
        [$type, $id] = explode(':', $request->input('destination'));
        $parent = $type === 'book' ? \BookStack\Entities\Models\Book::visible()->findOrFail($id) : null;
        $shelf = $type === 'shelf' ? Bookshelf::visible()->findOrFail($id) : null;
        app(\BookStack\View\LibraryMap::class)->place($book, $parent, $shelf);
        return redirect($book->getUrl());
    }
    protected $bookRepo;
    protected $entityContextManager;

    public function __construct(ShelfContext $entityContextManager, BookRepo $bookRepo)
    {
        $this->bookRepo = $bookRepo;
        $this->entityContextManager = $entityContextManager;
    }

    /**
     * Display a listing of the book.
     */
    public function index()
    {
        $view = setting()->getForCurrentUser('books_view_type');
        $sort = setting()->getForCurrentUser('books_sort', 'name');
        $order = setting()->getForCurrentUser('books_sort_order', 'asc');

        $books = $this->bookRepo->getAllPaginated(18, $sort, $order);
        $recents = $this->isSignedIn() ? $this->bookRepo->getRecentlyViewed(4) : false;
        $popular = $this->bookRepo->getPopular(4);
        $new = $this->bookRepo->getRecentlyCreated(4);

        $this->entityContextManager->clearShelfContext();

        $this->setPageTitle(trans('entities.books'));

        return view('books.index', [
            'books'   => $books,
            'recents' => $recents,
            'popular' => $popular,
            'new'     => $new,
            'view'    => $view,
            'sort'    => $sort,
            'order'   => $order,
        ]);
    }

    /**
     * Show the form for creating a new book.
     */
    public function create(string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = Bookshelf::visible()->where('slug', '=', $shelfSlug)->firstOrFail();
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
        }

        $this->setPageTitle(trans('entities.books_create'));

        return view('books.create', [
            'bookshelf' => $bookshelf,
        ]);
    }

    /**
     * Store a newly created book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     */
    public function store(Request $request, string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');
        $this->validate($request, [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'image'       => array_merge(['nullable'], $this->getImageValidationRules()),
        ]);

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = Bookshelf::visible()->where('slug', '=', $shelfSlug)->firstOrFail();
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
        }

        $book = $this->bookRepo->create($request->all());
        $this->bookRepo->updateCoverImage($book, $request->file('image', null));

        if ($bookshelf) {
            $bookshelf->appendBook($book, true);
            Activity::addForEntity($bookshelf, ActivityType::BOOKSHELF_UPDATE);
        }

        return redirect($book->getUrl());
    }

    /**
     * Display the specified book.
     */
    public function show(Request $request, string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $folderContents = app(\BookStack\ViewComponents\HierarchyFolder::class)->contents($book);
        $folderBooks = $folderContents['books'];
        $uploadedFiles = $folderContents['files'];
        $bookChildren = $folderContents['pages'];
        $bookParentShelves = $book->parent_book_id
            ? collect(app(\BookStack\View\LibraryMap::class)->ancestors($book))->filter(function ($parent) { return $parent instanceof Bookshelf; })
            : $book->shelves()->scopes('visible')->get();

        View::incrementFor($book);
        if ($request->has('shelf')) {
            $this->entityContextManager->setShelfContext(intval($request->get('shelf')));
        }

        $this->setPageTitle($book->getShortName());

        return view('books.show', [
            'folderBooks' => $folderBooks,
            'uploadedFiles' => $uploadedFiles,
            'book'              => $book,
            'current'           => $book,
            'bookChildren'      => $bookChildren,
            'bookParentShelves' => $bookParentShelves,
            'activity'          => Activity::entityActivity($book, 20, 1),
        ]);
    }

    /**
     * Show the form for editing the specified book.
     */
    public function edit(string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkOwnablePermission('book-update', $book);
        $this->setPageTitle(trans('entities.books_edit_named', ['bookName'=>$book->getShortName()]));

        return view('books.edit', ['book' => $book, 'current' => $book]);
    }

    /**
     * Update the specified book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(Request $request, string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkOwnablePermission('book-update', $book);
        $this->validate($request, [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'image'       => array_merge(['nullable'], $this->getImageValidationRules()),
        ]);

        $book = $this->bookRepo->update($book, $request->all());
        $resetCover = $request->has('image_reset');
        $this->bookRepo->updateCoverImage($book, $request->file('image', null), $resetCover);

        return redirect($book->getUrl());
    }

    /**
     * Shows the page to confirm deletion.
     */
    public function showDelete(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);
        $this->setPageTitle(trans('entities.books_delete_named', ['bookName' => $book->getShortName()]));

        return view('books.delete', ['book' => $book, 'current' => $book]);
    }

    /**
     * Remove the specified book from the system.
     *
     * @throws Throwable
     */
    public function destroy(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);

        $this->bookRepo->destroy($book);

        return redirect('/books');
    }

    /**
     * Show the permissions view.
     */
    public function showPermissions(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('restrictions-manage', $book);

        return view('books.permissions', [
            'book' => $book,
        ]);
    }

    /**
     * Set the restrictions for this book.
     *
     * @throws Throwable
     */
    public function permissions(Request $request, PermissionsUpdater $permissionsUpdater, string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('restrictions-manage', $book);

        $permissionsUpdater->updateFromPermissionsForm($book, $request);

        $this->showSuccessNotification(trans('entities.books_permissions_updated'));

        return redirect($book->getUrl());
    }
}
