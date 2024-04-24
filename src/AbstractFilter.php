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
    protected const FIELD_DELIMITER = '_';
    protected const FIELD_VALUE_DELIMITER = '__';

    public static int $defaultMaxLimit = 30;
    protected ?int $maxLimit = null;

    /** @var callable[]  */
    private array $queryCallbacks = [];
    /** @var FilterParam[]  */
    private array $filters = [];

    private function __construct(
        protected readonly Builder $builder,
        protected readonly Request $request
    ) {
    }

    public static function create(Builder $builder, Request $request): static
    {
        return new static($builder, $request);
    }

    final public function apply(Builder $builder): void
    {
        $this->parseFilters();
        $this->prepareQueryForFiltering($builder);
        $this->applyFilters($builder);
        $this->applyQueryCallback($builder);
        $this->finalize($builder);
    }

    final public function finalize(Builder $builder): void
    {
        $builder
            ->offset($this->request->input(self::PARAM_OFFSET, 0))
            ->limit($this->getLimit());
    }

    private function parseFilters(): void
    {
        $this->filters = [];
        foreach ($this->request->query() as $param => $value) {
            [$case, $field, $fieldValue] = $this->parseQueryParam($param);
            if (!$case || !$case = FilterCaseEnum::tryFrom($case)) {
                continue;
            }
            if ($case === FilterCaseEnum::FILTER) {
                if (!method_exists($this, $method = Str::camel($field) . 'Filter')) {
                    continue;
                }
                $field = $this->$method(...);
            }
            $filter = new FilterParam($case, $field, $value, $fieldValue);
            if (!$this->isFilterSuitable($filter)) {
                continue;
            }
            $this->filters[] = $filter;
        }
    }

    private function parseQueryParam(string $param): ?array
    {
        $case = strtok($param, self::FIELD_DELIMITER);
        if ($case === $param) {
            return null;
        }
        $field = substr($param, strlen($case . self::FIELD_DELIMITER));
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
        foreach ($this->filters as $filter) {
            $filter->apply($builder);
        }
    }
    abstract protected function getFieldsForFilter(FilterCaseEnum $filter): array;

    protected function prepareQueryForFiltering(Builder $builder): void
    {
    }

    private function getLimit(): int
    {
        $maxLimit = $this->maxLimit ?? static::$defaultMaxLimit ;

        return $this->request->input(self::PARAM_LIMIT, 0) > $maxLimit
            ? $maxLimit
            : $this->request->input(self::PARAM_LIMIT, $maxLimit);
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

    private function applyQueryCallback(Builder $builder): void
    {
        foreach ($this->queryCallbacks as $callback) {
            $callback($builder);
        }
    }

    final public function response(string|callable $resource = null): FilteredResponse
    {
        $this->apply($this->builder);
        $response = new FilteredResponse($this->builder);
        $response->setResource($resource);

        return $response;
    }

    final public function toArray(): array
    {
        return $this->response()->toArray();
    }

    private function isFilterSuitable(FilterParam $filterParam): bool
    {
        $fields = $this->getFieldsForFilter($filterParam->case);

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
