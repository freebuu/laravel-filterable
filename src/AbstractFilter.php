<?php

namespace FreeBuu\LaravelFilterable;

use FreeBuu\LaravelFilterable\Params\FilterParam;
use FreeBuu\LaravelFilterable\Params\FilterCaseEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @mixin Builder
 */
abstract class AbstractFilter implements Arrayable
{
    protected const PARAM_LIMIT = 'limit';
    protected const PARAM_OFFSET = 'offset';
    protected const PARAM_PURE = 'pure';
    protected const FIELD_DELIMITER = '_';
    protected const FIELD_VALUE_DELIMITER = '__';

    protected int $maxLimit = 30;

    /** @var callable[]  */
    private array $queryCallbacks = [];
    /** @var FilterParam[]  */
    private array $filters = [];

    /** @var string[]  */
    private array $scopes = [];

    /** @var string[]  */
    private array $excludeScopes = [];

    final private function __construct(
        protected readonly Builder $builder,
        protected readonly Request $request
    ) {
        $this->parseFilters();
    }

    public static function create(Builder $builder, Request $request): static
    {
        return new static($builder, $request);
    }

    final public function apply(Builder $builder): void
    {
        $this->prepareQueryForFiltering($builder);
        $this->applyFilters($builder);
        $this->applyQueryCallback($builder);
        $this->unsetRelationsOnPure($builder);
        $this->applyScopes($builder);
    }

    private function applyScopes(Builder $builder): void
    {
        $scopes = $this->scopes;

        foreach ($this->excludeScopes as $excludeScope) {
            unset($scopes[$excludeScope]);
        }

        $builder->scopes($scopes);
    }

    private function unsetRelationsOnPure(Builder $builder): void
    {
        $builder
            ->when($this->request->has(self::PARAM_PURE), fn (Builder $builder) => $builder->withoutEagerLoads());
    }

    private function paginate(Builder $builder): void
    {
        $builder
            ->offset($this->request->input(self::PARAM_OFFSET, 0))
            /** @phpstan-ignore-next-line */
            ->when($this->getLimit() > 0, fn (Builder $builder) => $builder->limit($this->getLimit()));
    }

    private function parseFilters(): void
    {
        $this->filters = [];
        $parseParams = function (string $source): array {
            $source = preg_replace_callback(
                '/(^|(?<=&))[^=[&]+/',
                fn ($key) => bin2hex(urldecode($key[0])),
                $source
            );
            parse_str($source, $params);

            return collect($params)
                ->mapWithKeys(fn ($value, $key) => [hex2bin($key) => $value])
                ->toArray();
        };
        if (!$query = $this->request->getQueryString()) {
            return;
        }
        foreach ($parseParams($query) as $param => $value) {
            if (![$case, $field, $fieldValue] = $this->parseQueryParam($param)) {
                continue;
            }
            if ($case === FilterCaseEnum::FILTER) {
                if (!method_exists($this, $method = 'filter' . Str::studly($field))) {
                    continue;
                }
                $field = $this->$method(...);
            } elseif (method_exists($this, $method = 'override' . Str::studly($case->value) . Str::studly($field))) {
                $case = FilterCaseEnum::FILTER;
                $field = $this->$method(...);
            }
            $filter = new FilterParam($case, $field, $value, $fieldValue);
            if (!$this->isFilterSuitable($filter)) {
                continue;
            }
            $this->filters[] = $filter;
        }
    }

    /** @return array{FilterCaseEnum, string, mixed}|null */
    private function parseQueryParam(string $param): ?array
    {
        $case = null;
        foreach (FilterCaseEnum::cases() as $possibleCase) {
            if (str_starts_with($param, $possibleCase->value)) {
                $case = $possibleCase;
                break;
            }
        }
        if (!$case) {
            return null;
        }

        $field = substr($param, strlen($case->value . self::FIELD_DELIMITER));
        $fieldValue = null;
        $fieldValueCheck = explode(self::FIELD_VALUE_DELIMITER, $field);
        if (count($fieldValueCheck) === 2) {
            $field = $fieldValueCheck[0];
            $fieldValue = $fieldValueCheck[1];
        }

        return [$case, $field, $fieldValue];
    }

    private function applyFilters(Builder $builder): void
    {
        //TODO некрасиво, но б-г с ним
        $hasSorting = false;
        foreach ($this->filters as $filter) {
            if ($filter->case === FilterCaseEnum::SORT) {
                $hasSorting = true;
            }
            $filter->apply($builder);
        }
        if ($hasSorting === false) {
            $this->defaultSorting($builder);
        }
    }
    abstract protected function getFilterableFields(FilterCaseEnum $case): array;

    protected function prepareQueryForFiltering(Builder $builder): void
    {
    }

    protected function defaultSorting(Builder $builder): void
    {
    }

    private function getLimit(): int
    {
        $maxLimit = $this->maxLimit;
        $requestLimit = $this->request->input(self::PARAM_LIMIT) ?: $maxLimit;

        return ($maxLimit > 0 && $requestLimit > $maxLimit) ? $maxLimit : $requestLimit;
    }

    public function setMaxLimit(int $maxLimit): self
    {
        $this->maxLimit = $maxLimit;

        return $this;
    }

    final public function __call(string $name, array $arguments): static
    {
        return $this->addQueryCallback(fn ($builder) => $builder->$name(...$arguments));
    }

    final public function addQueryCallback(callable $queryCallback): static
    {
        $this->queryCallbacks[] = $queryCallback;

        return $this;
    }

    final public function addScope(string $scope, array $params = []): static
    {
        $this->scopes[$scope] = $params;

        return $this;
    }

    final public function excludeScope(string $scope): static
    {
        $this->excludeScopes[] = $scope;

        return $this;
    }

    private function applyQueryCallback(Builder $builder): void
    {
        foreach ($this->queryCallbacks as $callback) {
            $callback($builder);
        }
    }

    final public function response(string|callable|null $resource = null): FilteredResponse
    {
        $builder = $this->getBuilder();
        $this->paginate($builder);
        $response = new FilteredResponse($builder);
        $response->setResource($resource);

        return $response;
    }

    final public function jsonResponse(string|callable|null $resource = null): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->response($resource));
    }

    final public function toArray(): array
    {
        return $this->response()->toArray();
    }

    final public function getBuilder(): Builder
    {
        $builder = clone $this->builder;
        $this->apply($builder);

        return $builder;
    }

    private function isFilterSuitable(FilterParam $filterParam): bool
    {
        $fields = $this->getFilterableFields($filterParam->case);

        if ($filterParam->case === FilterCaseEnum::FILTER) {
            return true;
        }
        if ($filterParam->fieldValueMandatory()) {
            if (!$filterParam->fieldValue) {
                return false;
            }
            if (!$fieldValues = $fields[$filterParam->field] ?? null) {
                return false;
            }

            return is_array($fieldValues) && array_is_list($fieldValues) && in_array($filterParam->fieldValue, $fieldValues);
        } else {
            return array_is_list($fields) && in_array($filterParam->field, $fields);
        }
    }
}
