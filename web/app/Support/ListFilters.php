<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The server half of the shared search-and-filter bar (PB-54; the React half is
 * components/ListFilters.jsx). Every list page reads its filters through this class:
 *
 *   $filters = ListFilters::from($request, ['status' => array_keys(Campaign::STATUSES)]);
 *   $query->when($filters->get('status'), fn ($q, $status) => $q->where('status', $status));
 *   $filters->applySearch($query, ['title', 'borrower.name']);
 *   return Inertia::render('...', ['campaigns' => ..., 'filters' => $filters->toArray()]);
 *
 * Values come from the URL, so anyone can type anything there. Each filter has a fixed list of
 * allowed values and anything else is ignored (treated as "all"), never an error: an old or
 * edited link still opens the list. The search text is trimmed, limited to 100 characters, and
 * matched literally (a typed % or _ is not a wildcard).
 */
final class ListFilters
{
    public const SEARCH = 'search';

    public const MAX_SEARCH_LENGTH = 100;

    /**
     * @param  array<string, string>  $values  the valid filter values, by name
     */
    private function __construct(private readonly ?string $search, private readonly array $values) {}

    /**
     * @param  array<string, list<string>>  $allowed  filter name => the values it accepts
     */
    public static function from(Request $request, array $allowed = []): self
    {
        $search = $request->query(self::SEARCH);
        $search = is_string($search) ? Str::limit(trim($search), self::MAX_SEARCH_LENGTH, '') : '';

        $values = [];
        foreach ($allowed as $name => $options) {
            $value = $request->query($name);
            if (is_string($value) && in_array($value, $options, true)) {
                $values[$name] = $value;
            }
        }

        return new self($search === '' ? null : $search, $values);
    }

    public function search(): ?string
    {
        return $this->search;
    }

    /**
     * A filter's value, or null for "all" (missing or not allowed).
     */
    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    /**
     * Narrow the query to rows where any of the columns contains the search text. A column can
     * be on a relation ("borrower.name", "borrower.borrowerProfile.business_name").
     *
     * @param  list<string>  $columns
     */
    public function applySearch(QueryBuilder $query, array $columns): QueryBuilder
    {
        if ($this->search === null) {
            return $query;
        }

        $pattern = '%'.self::escapeLike($this->search).'%';

        return $query->where(function ($query) use ($columns, $pattern) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    $relation = Str::beforeLast($column, '.');
                    $field = Str::afterLast($column, '.');
                    $query->orWhereHas($relation, fn ($related) => $related->where($field, 'like', $pattern));
                } else {
                    $query->orWhere($column, 'like', $pattern);
                }
            }
        });
    }

    /**
     * Whether anything is being filtered, for "no results for these filters" messages.
     */
    public function active(): bool
    {
        return $this->search !== null || $this->values !== [];
    }

    /**
     * The current values for the page, so the bar shows what the list is filtered by:
     * { search: '...', status: 'live', ... }, with only the filters that are set.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([self::SEARCH => $this->search, ...$this->values], fn ($value) => $value !== null);
    }

    /**
     * ['key' => 'Label'] → [{ value, label }] for a filter's dropdown.
     *
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $labels): array
    {
        return array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => $label], array_keys($labels), $labels);
    }

    /**
     * Make % and _ (and the escape character itself) match literally in a LIKE pattern. MySQL's
     * default escape character is the backslash.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
