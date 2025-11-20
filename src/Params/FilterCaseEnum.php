<?php

namespace FreeBuu\LaravelFilterable\Params;

enum FilterCaseEnum: string
{
    case FROM           = 'from';
    case TO             = 'to';
    case SORT           = 'sort';
    case SEARCH         = 'search';
    case WHERE_HAS_ALL  = 'where_has_all';
    case WHERE_HAS      = 'where_has';
    case WHERE_NOT      = 'where_not';
    case WHERE          = 'where';
    case START_WITH     = 'start_with';
    case FILTER         = 'filter';
}
