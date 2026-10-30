<?php

namespace BookStack\Entities\Tools;

use Illuminate\Http\Request;

class SearchOptions
{
    public $documentScope = null;
    public $rawQuery = '';
    public $matchAllTerms = false;

    public static function fromUserString(string $search): self
    {
        $instance = new self();
        foreach (static::decode($search, true) as $type => $value) {
            $instance->$type = $value;
        }
        $instance->rawQuery = $search;
        $instance->matchAllTerms = true;
        return $instance;
    }

    /**
     * @var array
     */
    public $searches = [];

    /**
     * @var array
     */
    public $exacts = [];

    /**
     * @var array
     */
    public $tags = [];

    /**
     * @var array
     */
    public $filters = [];

    /**
     * Create a new instance from a search string.
     */
    public static function fromString(string $search): self
    {
        $decoded = static::decode($search);
        $instance = new SearchOptions();
        foreach ($decoded as $type => $value) {
            $instance->$type = $value;
        }

        return $instance;
    }

    /**
     * Create a new instance from a request.
     * Will look for a classic string term and use that
     * Otherwise we'll use the details from an advanced search form.
     */
    public static function fromRequest(Request $request): self
    {
        if (!$request->has('search') && !$request->has('term')) {
            return static::fromString('');
        }

        if ($request->has('term')) {
            return static::fromUserString($request->get('term'));
        }

        $instance = new SearchOptions();
        $instance->rawQuery = $request->input('search', '');
        $instance->matchAllTerms = true;
        $inputs = $request->only(['search', 'types', 'filters', 'exact', 'tags']);

        $parsedStandardTerms = static::fromUserString($inputs['search'] ?? '');
        $instance->searches = $parsedStandardTerms->searches;
        $instance->exacts = $parsedStandardTerms->exacts;

        array_push($instance->exacts, ...array_filter($inputs['exact'] ?? []));

        $instance->tags = array_filter($inputs['tags'] ?? []);

        foreach (($inputs['filters'] ?? []) as $filterKey => $filterVal) {
            if (empty($filterVal)) {
                continue;
            }
            $instance->filters[$filterKey] = $filterVal === 'true' ? '' : $filterVal;
        }

        if (isset($inputs['types']) && count($inputs['types']) < 4) {
            $instance->filters['type'] = implode('|', $inputs['types']);
        }

        return $instance;
    }

    /**
     * Decode a search string into an array of terms.
     */
    protected static function decode(string $searchString, bool $natural = false): array
    {
        $terms = [
            'searches' => [],
            'exacts'   => [],
            'tags'     => [],
            'filters'  => [],
        ];

        $patterns = [
            'exacts'  => '/"(.*?)"/',
            'tags'    => '/\[(.*?)\]/',
            'filters' => '/\{(.*?)\}/',
        ];

        // Parse special terms
        foreach ($patterns as $termType => $pattern) {
            $matches = [];
            preg_match_all($pattern, $searchString, $matches);
            if (count($matches) > 0) {
                $terms[$termType] = $matches[1];
                $searchString = preg_replace($pattern, '', $searchString);
            }
        }

        // Parse standard terms
        $parsedStandardTerms = static::parseStandardTermString($searchString, $natural);
        array_push($terms['searches'], ...$parsedStandardTerms['terms']);
        array_push($terms['exacts'], ...$parsedStandardTerms['exacts']);

        // Split filter values out
        $splitFilters = [];
        foreach ($terms['filters'] as $filter) {
            $explodedFilter = explode(':', $filter, 2);
            $splitFilters[$explodedFilter[0]] = (count($explodedFilter) > 1) ? $explodedFilter[1] : '';
        }
        $terms['filters'] = $splitFilters;

        return $terms;
    }

    /**
     * Parse a standard search term string into individual search terms and
     * extract any exact terms searches to be made.
     *
     * @return array{terms: array<string>, exacts: array<string>}
     */
    protected static function parseStandardTermString(string $termString, bool $natural = false): array
    {
        if ($natural) {
            $terms = [];
            $term = strtok($termString, SearchIndex::$delimiters);
            while ($term !== false) {
                $terms[] = $term;
                $term = strtok(SearchIndex::$delimiters);
            }
            return ['terms' => array_values(array_unique($terms)), 'exacts' => []];
        }
        $terms = explode(' ', $termString);
        $indexDelimiters = SearchIndex::$delimiters;
        $parsed = [
            'terms'  => [],
            'exacts' => [],
        ];

        foreach ($terms as $searchTerm) {
            if ($searchTerm === '') {
                continue;
            }

            $parsedList = (strpbrk($searchTerm, $indexDelimiters) === false) ? 'terms' : 'exacts';
            $parsed[$parsedList][] = $searchTerm;
        }

        return $parsed;
    }

    /**
     * Encode this instance to a search string.
     */
    public function toString(): string
    {
        $string = implode(' ', $this->searches ?? []);

        foreach ($this->exacts as $term) {
            $string .= ' "' . $term . '"';
        }

        foreach ($this->tags as $term) {
            $string .= " [{$term}]";
        }

        foreach ($this->filters as $filterName => $filterVal) {
            $string .= ' {' . $filterName . ($filterVal ? ':' . $filterVal : '') . '}';
        }

        return $string;
    }
}
