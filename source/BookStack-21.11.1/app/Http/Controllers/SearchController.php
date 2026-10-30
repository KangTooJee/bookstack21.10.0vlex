<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Queries\Popular;
use BookStack\Entities\Tools\SearchOptions;
use BookStack\Entities\Tools\SearchResultsFormatter;
use BookStack\Entities\Tools\SearchRunner;
use BookStack\Entities\Tools\SearchScope;
use BookStack\Entities\Tools\SiblingFetcher;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected $searchRunner;
    protected $entityContextManager;

    public function __construct(SearchRunner $searchRunner)
    {
        $this->searchRunner = $searchRunner;
    }

    /**
     * Searches all entities.
     */
    public function search(Request $request, SearchResultsFormatter $formatter)
    {
        $searchOpts = SearchOptions::fromRequest($request);
        $runSearch = $request->hasAny(['term', 'search', 'scope']);
        if ($runSearch) {
            $this->validate($request, ['scope' => SearchScope::rules()], [
                'scope.required' => 'Vui lòng chọn phạm vi tìm kiếm.',
                'scope.in' => 'Phạm vi tìm kiếm không hợp lệ.',
            ]);
            $searchOpts->documentScope = $request->input('scope');
        }
        $fullSearchString = $searchOpts->rawQuery;
        $this->setPageTitle(trans('entities.search_for_term', ['term' => $fullSearchString]));

        $page = intval($request->get('page', '0')) ?: 1;
        $nextPageLink = url('/search?' . http_build_query(array_merge($request->query(), ['scope' => $searchOpts->documentScope, 'page' => $page + 1])));

        $results = $runSearch
            ? $this->searchRunner->searchEntities($searchOpts, 'all', $page, 20)
            : ['results' => collect(), 'total' => 0, 'has_more' => false];
        $formatter->format($results['results']->all(), $searchOpts);

        return view('search.all', [
            'entities'     => $results['results'],
            'totalResults' => $results['total'],
            'searchTerm'   => $fullSearchString,
            'hasNextPage'  => $results['has_more'],
            'nextPageLink' => $nextPageLink,
            'options'      => $searchOpts,
        ]);
    }

    /**
     * Searches all entities within a book.
     */
    public function searchBook(Request $request, int $bookId)
    {
        $this->validate($request, ['scope' => SearchScope::rules()]);
        $term = $request->get('term', '');
        $results = $this->searchRunner->searchBook($bookId, $term, $request->input('scope'));

        return view('entities.list', ['entities' => $results]);
    }

    /**
     * Searches all entities within a chapter.
     */
    public function searchChapter(Request $request, int $chapterId)
    {
        $this->validate($request, ['scope' => SearchScope::rules()]);
        $term = $request->get('term', '');
        $results = $this->searchRunner->searchChapter($chapterId, $term, $request->input('scope'));

        return view('entities.list', ['entities' => $results]);
    }

    /**
     * Search for a list of entities and return a partial HTML response of matching entities.
     * Returns the most popular entities if no search is provided.
     */
    public function searchEntitiesAjax(Request $request)
    {
        $entityTypes = $request->filled('types') ? explode(',', $request->get('types')) : ['page', 'chapter', 'book'];
        $searchTerm = $request->get('term', false);
        $permission = $request->get('permission', 'view');

        // Search for entities otherwise show most popular
        if ($searchTerm !== false) {
            $searchTerm .= ' {type:' . implode('|', $entityTypes) . '}';
            $entities = $this->searchRunner->searchEntities(SearchOptions::fromString($searchTerm), 'all', 1, 20, $permission)['results'];
        } else {
            $entities = (new Popular())->run(20, 0, $entityTypes, $permission);
        }

        return view('search.parts.entity-ajax-list', ['entities' => $entities]);
    }

    /**
     * Search siblings items in the system.
     */
    public function searchSiblings(Request $request)
    {
        $type = $request->get('entity_type', null);
        $id = $request->get('entity_id', null);

        $entities = (new SiblingFetcher())->fetch($type, $id);

        return view('entities.list-basic', ['entities' => $entities, 'style' => 'compact']);
    }
}
