<?php

namespace Tests\Unit;

use FreeBuu\LaravelFilterable\Params\FilterCaseEnum;
use FreeBuu\LaravelFilterable\Params\FilterParam;
use Illuminate\Database\Eloquent\Builder;
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
        $filter = new FilterParam($case, 'some-field', $value);
        $this->assertEquals($expected, $filter->normalizedValue());
    }

    public function provideTestApply(): array
    {
        return [
            [FilterCaseEnum::FROM, 'where', '>='],
            [FilterCaseEnum::TO, 'where', '<='],
            [FilterCaseEnum::SORT, 'sort'],
            [FilterCaseEnum::SEARCH, 'where', FilterParam::$likeFunction],
            [FilterCaseEnum::START_WITH, 'where', FilterParam::$likeFunction],
            [FilterCaseEnum::WHERE, 'whereIn'],
        ];
    }

    /** @dataProvider provideTestApply */
    public function testApply(FilterCaseEnum $case, string $method, string $operator = null)
    {
        $filter = new FilterParam($case, 'some-field', 'some_value');
        $builder = \Mockery::mock(Builder::class);
        $builder
            ->shouldReceive($method)
            ->withArgs(array_values(array_filter([$filter->field, $operator, $filter->normalizedValue()])));
        dump(array_filter([$filter->field, $operator, $filter->normalizedValue()]));
        $filter->apply($builder);
        $this->addToAssertionCount(1);
    }

    public function testApplyFilter()
    {
        $builder = \Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->with('some_value', 'some_field_value');

        $callback = function ($builder, $value, $filedValue) {
            $builder->where($value, $filedValue);
        };

        $filter = new FilterParam(
            FilterCaseEnum::FILTER,
            $callback,
            'some_value',
            'some_field_value'
        );
        $filter->apply($builder);
        $this->addToAssertionCount(1);
    }
}