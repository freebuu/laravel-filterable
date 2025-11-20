<?php

namespace FreeBuu\LaravelFilterable\Params;

use Illuminate\Database\Eloquent\Builder;

final class FilterParam
{
    public static string $likeFunction = 'like';

    public const WHERE_DELIMITER = ',';

    public function __construct(
        public readonly FilterCaseEnum  $case,
        public readonly \Closure|string $field,
        public readonly mixed           $value = null,
        public readonly mixed           $fieldValue = null,
    ) {
    }

    /**
     * @return string|int|array|bool|null
     */
    private function normalizedValue(): string|int|array|bool|null
    {
        if (
            $this->value === 'null'
            || $this->value === ''
            || is_null($this->value)
        ) {
            return null;
        }
        $normalizedValue = match ($this->case) {
            FilterCaseEnum::WHERE, FilterCaseEnum::WHERE_HAS, FilterCaseEnum::WHERE_HAS_ALL, FilterCaseEnum::WHERE_NOT => explode(self::WHERE_DELIMITER, $this->value),
            FilterCaseEnum::SEARCH => '%' . str_replace(' ', '%', $this->value) . '%',
            FilterCaseEnum::START_WITH => str_contains($this->value, ' ') ? null : $this->value . '%',
            FilterCaseEnum::TO, FilterCaseEnum::FROM => is_numeric($this->value) ? $this->value : null,
            FilterCaseEnum::SORT => in_array($this->value, ['desc', 'asc']) ? $this->value : null,
            FilterCaseEnum::FILTER => $this->value
        };

        return match ($normalizedValue) {
            "true" => true,
            "false" => false,
            default => $normalizedValue,
        };
    }

    public function apply(Builder $builder): void
    {
        $value = $this->normalizedValue();
        if (is_null($value)) {
            return;
        }
        match ($this->case) {
            FilterCaseEnum::FROM => $builder->where($this->field, '>=', $value),
            FilterCaseEnum::TO => $builder->where($this->field, '<=', $value),
            FilterCaseEnum::SORT => $builder->orderBy($this->field, $value),
            FilterCaseEnum::SEARCH, FilterCaseEnum::START_WITH => $builder->where($this->field, self::$likeFunction, $value),
            FilterCaseEnum::WHERE => $builder->whereIn($this->field, $value),
            FilterCaseEnum::WHERE_NOT => $builder->whereNotIn($this->field, $value),
            FilterCaseEnum::WHERE_HAS => $builder->whereHas($this->field, fn (Builder $builder) => $builder->whereIn($builder->getModel()->getTable() . '.' . $this->fieldValue, $value)),
            FilterCaseEnum::WHERE_HAS_ALL => collect($value)->each(fn ($value) => $builder->whereHas($this->field, fn (Builder $builder) => $builder->where($builder->getModel()->getTable() . '.' . $this->fieldValue, $value))),
            FilterCaseEnum::FILTER => call_user_func_array($this->field, [$builder, $value, $this->fieldValue]),
        };
    }

    public function fieldValueMandatory(): bool
    {
        return match ($this->case) {
            FilterCaseEnum::WHERE_HAS, FilterCaseEnum::WHERE_HAS_ALL => true,
            default => false
        };
    }
}
