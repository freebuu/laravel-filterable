<?php

namespace Tests\Unit;

use FreeBuu\LaravelFilterable\Params\FilterCaseEnum;
use FreeBuu\LaravelFilterable\Params\FilterParam;
use Tests\TestCase;

class FilterParamTest extends TestCase
{
    public function provideTestNormalizeValues(): array
    {
        return [
            [FilterCaseEnum::WHERE, null, null],
            [FilterCaseEnum::WHERE, '', null],
            [FilterCaseEnum::WHERE, 'value', ['value']],
            [FilterCaseEnum::WHERE, 'value,newValue', ['value', 'newValue']],
            [FilterCaseEnum::SEARCH, 'some text', '%some%text%'],
            [FilterCaseEnum::START_WITH, 'some text', null],
            [FilterCaseEnum::START_WITH, 'some', 'some%'],
            [FilterCaseEnum::SORT, 'some', null],
            [FilterCaseEnum::SORT, 'asc', 'asc'],
            [FilterCaseEnum::SORT, 'desc', 'desc'],
            [FilterCaseEnum::FROM, 'abc', null],
            [FilterCaseEnum::FROM, '10', 10],

        ];
    }

    /** @dataProvider provideTestNormalizeValues */
    public function testNormalizeValues(FilterCaseEnum $case, mixed $value, mixed $expected): void
    {
        $param = new FilterParam($case, 'some-field', $value);
        $this->assertEquals($expected, $param->normalizedValue());
    }
}