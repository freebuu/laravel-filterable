<?php

namespace FreeBuu\LaravelFilterable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

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
        $query = $model->newQuery();
        /** @var AbstractFilter $filter */
        $filter = App::make($model->requestFilterClass());
        $filter->setBuilder($query);
        if ($request) {
            $filter->setRequest($request);
        }
        return $filter;
    }
}
