<?php

namespace FreeBuu\LaravelFilterable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** @mixin Model */
trait HasRequestFilter
{
    final public static function requestFilter(?Request $request = null): AbstractFilter
    {
        $model = new self();
        /** @phpstan-ignore function.alreadyNarrowedType, function.impossibleType */
        $filterClass = property_exists($model, 'requestFilter') ? $model->requestFilter : BasicFilter::class;
        if (!is_a($filterClass, AbstractFilter::class, true)) {
            throw new \InvalidArgumentException(sprintf('Class %s must extend %s', $filterClass, AbstractFilter::class));
        }

        return $filterClass::create($model->newQuery(), $request ?? request());
    }
}
