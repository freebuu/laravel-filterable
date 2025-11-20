<?php

namespace FreeBuu\LaravelFilterable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FilteredResponse implements Arrayable
{
    private Builder $builder;
    private mixed $resource = null;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function setResource(string|callable|null $resource): FilteredResponse
    {
        $this->resource = $resource;

        return $this;
    }

    public function compile(): array
    {
        return [
            'meta' => [
                'limit' => $this->builder->getQuery()->limit,
                'offset' => $this->builder->getQuery()->offset,
                'total' => $this->getCountForPagination($this->builder),
            ],
            'data' => $this->transformData($this->builder->get()),
        ];
    }

    private function getCountForPagination(Builder $builder): int
    {
        return $builder->clone()->tap(function ($eloquentBuilder) {
            $eloquentBuilder->getQuery()->tap(function ($queryBuilder) {
                $queryBuilder->limit = null;
                $queryBuilder->offset = null;
                $queryBuilder->orders = null;
                $queryBuilder->unions = null;
            });
        })->count();
    }

    private function transformData(Collection $data): mixed
    {
        if (is_callable($this->resource)) {
            $data = call_user_func_array($this->resource, [$data]);
        } elseif (!is_null($this->resource) && method_exists($this->resource, 'collection')) {
            $data = $this->resource::collection($data);
        }

        return $data;
    }

    public function toArray(): array
    {
        return $this->compile();
    }
}
