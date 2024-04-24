<?php

namespace FreeBuu\LaravelFilterable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** @mixin Model */
trait HasRequestFilter
{
    protected function requestFilterClass(): string
    {
        return BasicFilter::class;
    }

    public static function requestFilter(Request $request = null): AbstractFilter
    {
        $model = new self();
        /** @var AbstractFilter $filter */
        $filter = $model->requestFilterClass();
        return $filter::create($model->newQuery(), $request ?? request());
    }
}
