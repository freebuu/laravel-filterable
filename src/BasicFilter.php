<?php

namespace FreeBuu\LaravelFilterable;

use FreeBuu\LaravelFilterable\Params\FilterCaseEnum;

final class BasicFilter extends AbstractFilter
{
    protected function getFieldsForFilter(FilterCaseEnum $filter): array
    {
        return [];
    }
}
