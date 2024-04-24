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
     * @note very-very bad place
     * @return string|int|array|bool|null
     */
    public function normalizedValue(): string|int|array|bool|null
    {
        if($this->value === '') {
            return null;
        }
        if (is_null($this->value)) {
            return null;
        }
        if ($this->case === FilterCaseEnum::WHERE || $this->case === FilterCaseEnum::WHERE_HAS) {
            return explode(self::WHERE_DELIMITER, $this->value);
        }
        if ($this->case === FilterCaseEnum::SEARCH) {
            return '%' . str_replace(' ', '%', $this->value) . '%';
        }
        if ($this->case === FilterCaseEnum::START_WITH) {
            if (str_contains($this->value, ' ')) {
                return null;
            }
            return $this->value . '%';
        }
        if($this->case === FilterCaseEnum::TO || $this->case === FilterCaseEnum::FROM) {
            if(! is_numeric($this->value)) {
                return null;
            }
            return $this->value;
        }
        if($this->case === FilterCaseEnum::SORT) {
            if(! in_array($this->value, ['desc', 'asc'])) {
                return null;
            }
            return $this->value;
        }

        return match ($this->value) {
            "true" => true,
            "false" => false,
            default => $this->value,
        };
    }

    public function apply(Builder $builder): void
    {
        if (! $value = $this->normalizedValue()) {
            return;
        }
        match ($this->case) {
            FilterCaseEnum::FROM => $builder->where($this->field, '>=', $value),
            FilterCaseEnum::TO => $builder->where($this->field, '<=', $value),
            FilterCaseEnum::SORT => $builder->orderBy($this->field, $value),
            FilterCaseEnum::SEARCH, FilterCaseEnum::START_WITH => $builder->where($this->field, self::$likeFunction, $value),
            FilterCaseEnum::WHERE => $builder->whereIn($this->field, $value),
            FilterCaseEnum::WHERE_HAS => $builder->whereHas($this->field, fn (Builder $builder) => $builder->whereIn($this->fieldValue, $value)),
            FilterCaseEnum::FILTER => call_user_func_array($this->field, [$builder, $this->value, $this->fieldValue]),
        };
    }

    public function fieldValueMandatory(): bool
    {
        return match ($this->case) {
            FilterCaseEnum::WHERE_HAS => true,
            default => false
        };
    }
}
