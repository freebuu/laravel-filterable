# Laravel Filterable
Simple filter and paginate index data. Main idea - KISS.

Example query:
```
?search_description=some%20text&sort_id=desc&where_publisher_id=1,23&where_has_groups__id=30,40&limit=10&offset=20
```
In code this query reflects:
- **search_description=some%20text** - `$builder->where('description', 'like', '%some%text%')`
- **sort_id=desc** - `$builder->sortBy('id', 'desc')`
- **where_publisher_id=1,23** - `$builder->whereIn('publisher_id', [1,23])`
- **where_has_groups__id=30,40** - `$builder->whereHas('groups', fn($builder) => $builder->whereIn('id', [30,40]))`
- **limit=10** - `$builder->limit(10)`
- **offset=20** - `$builder->offset(20)`

## Installation
Requires laravel >= 9 and php ^8.1

```bash
composer require freebuu/laravel-filterable
```

## Basic usage (pagination only)
Add [HasRequestFilter](src/HasRequestFilter.php) trait to Model - that's all.
```php
class PostIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        return Post::requestFilter()->jsonResponse(PostResource::class)
    }
}
```
Example result for query `/api/posts/?limit=25&offset=10`
```json lines
{
    "meta": {
        "limit": 25,
        "offset": 10,
        "total": 2
    },
    "data": [
        {
            "id": "1",
            "title": "post title"
        },
        {
            "id": "2",
            "title": "another post title"
        }
    ]
}
```
- `meta` - object with pagination data
- `data` - array with model data, wrapped in `PostResource` 
## Filtration
For filtration, you need to create a filter class for each model. Filter class must extend [AbstractFilter](src/AbstractFilter.php). Best place for these classes is `App\Http\Filters`.

In method `getFilterableFields()` you specify which fields can be filtered in each filter case.

_**HINT**_ - always add `default` state because filter cases may be supplemented.
```php
class PostFilter extends AbstractFilter
{
    protected function getFilterableFields(FilterCaseEnum $case): array
    {
        return match ($case) {
            FilterCaseEnum::WHERE => ['publisher_id'],
            FilterCaseEnum::SORT => ['id'],
            FilterCaseEnum::WHERE_HAS => ['groups' => ['id']]
            default => []
        };
    }
}
```
To set this filter for `Model` - add property `requestFilter`
```php
class Post extends Model
{
    use HasRequestFilter;
    
    protected string $requestFilter = PostFilter::class;
}
```

### Filter case
Filterable query params contains four parts separated with `_`. Let's see example with `where_has_groups__id=30,40`
- **$case** - where_has
- **$field** - groups
- **$fieldValue** - id (optional, mandatory only with **where_has**)
- **$value** - 30,40

In code, they are presented as [FilterCaseEnum.php](src/Params/FilterCaseEnum.php) and they work like this
- **FROM** 
  - Accepts only int
  - `$builder->where($field, '>=', $value)`
- **TO**
  - Accepts only int
  - `$builder->where($field, '<=', $value)`- 
- **SORT** - sorting
    - Accepts only `asc`, `desc`
  - `$builder->sortBy($field, $value)`
- **SEARCH** - search with `like` operator
  - Accept string with spaces. Add `%` at start, end and instead of all spaces
  - `$builder->where($field', 'like', $value)`
- **START_WITH** - all strings starts with passed value
  - Accept string without spaces. Add `%` at end of value
  - `$builder->where($field', 'like', $value)`
- **WHERE_HAS** - filter by relation with array of values
  - Accept comma separated array of values.
  - For this filter `fieldValue` fields must be set (see in example in `PostFilter`)
  - `$builder->whereHas($field, fn($builder) => $builder->whereIn($fieldValue, $value))`
- **WHERE** - filter by array of values
  - Accept comma separated array of values.
  - `$builder->whereIn($fieldValue, $value)`
- **FILTER** - uses for custom filters, see below

### Custom filters
In filter class you can make custom filter by creating a method like `filterCustom` - it **must** begin with `filter`. Then yoy can use it in query like `?filter_custom=123`

_**HINT**_ - you can use `fieldValue` here like `?filter_custom__alias=123` - it pass as third parameter in filter method.
```php
class PostFilter extends AbstractFilter
{
    public function filterCustom(Builder $builder, mixed $value, mixed $fieldValue): void
    {
        //you have request instance here
        if($this->request->query('something')){
            return;
        }
        //$value will be 123 
        //$fieldValue will be alias (or can be null)
        $builder->where('some_field', $value)->where($fieldValue, '432')
    }
}
```
## Advanced usage

## Limit
For security reasons, the `limit` field is set to a maximum value. If the request specifies a value greater, it will be reset to the default value.
- Default it set to `30`
- You can override this value in Filter class - overwrite the `maxLimit` property.
- Or you can override it system-wide in `AppServiceProvider`
```php
class AppServiceProvider extends ServiceProvider
{
    public function register() 
    {
        AbstractFilter::$defaultMaxLimit = 50;
    }
}
```

## Query callbacks 
Sometimes you need to set up query condition situational—e.g. filter only for auth user
```php
Post::requestFilter()
    ->addQueryCallback(fn (Builder $builder) => $builder->where('author_id', auth()->id()))
    ->response(ResourceClass::class);
```
Or just use `Builder` mixin:
```php
Post::requestFilter()
    ->where('author_id', auth()->id())
    ->response(ResourceClass::class);
```