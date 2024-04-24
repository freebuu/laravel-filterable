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
                'total' => $this->builder->getQuery()->getCountForPagination(),
            ],
            'data' => $this->transformData($this->builder->get()),
        ];
    }

    private function transformData(Collection $data): mixed
    {
        if (is_callable($this->resource)) {
            $data = call_user_func_array($this->resource, [$data]);
        } elseif (method_exists($this->resource, 'collection')) {
            $data = $this->resource::collection($data);
        }

        return $data;
    }

    public function toArray(): array
    {
        return $this->compile();
    }
}
