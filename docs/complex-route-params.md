# Complex Route Parameters

This section covers advanced configurations for mapping route parameters in scenarios where properties alone are not
sufficient. You'll learn how to work with nested properties, collections, non-property values, and dynamically provided
values to create flexible and powerful purge rules.

## Using Nested Properties

You can access nested properties of an entity to define route parameters by
using [Symfony's PropertyAccess](https://symfony.com/doc/current/components/property_access.html) syntax:

```php
#[Route('/author/{id<\d+>}', name: 'author_details', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['id' => 'author.id'])]
public function detailsAction(Author $author)
{
}
```

If the nested object is nullable, you can use the null-safe operator (`?`) to skip generating the URL if the nested
property is `null`:

```php
#[Route('/author/{id<\d+>}', name: 'author_details', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['id' => 'author?.id'])]
public function detailsAction(Author $author)
{
}
```

If the route parameter itself is optional, the URL will be generated without it.

## Using a Collection of Objects

When working with collections, you can map route parameters to each element within the collection:

```php
#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Author::class, routeParams: ['id' => 'posts[*].id'])]
public function detailsAction(Post $post)
{
}
```

In this example, the `id` parameter is extracted from each item in the `posts` collection, generating a URL for each
individual post. This ensures that each item in the collection has its corresponding URL purged.

When working with multiple collections, the bundle generates
a [Cartesian product](https://en.wikipedia.org/wiki/Cartesian_product) of URLs, ensuring that all combinations of
elements within the collections are purged:

```php
#[Route('/posts/{tag}/{commentId<\d+>}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['tag' => 'tags[*].id', 'commentId' => 'comments[*].id'])]
public function listAction(string $tag, Comment $comment)
{
}
```

In this example, URLs are generated for all combinations of `tag` and `commentId` based on the elements in the `tags`
and `comments` collections. This ensures that each unique combination within the collections is accounted for.

## Using Non-Property Values as Route Parameters

In addition to mapping entity properties to route parameters, you can use various other value types. This flexibility
allows you to customize routes based on raw values, backed enums, or even dynamic values provided by services.

### Using Raw Values

You can specify raw values directly in the route parameters:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;

#[Route('/posts/{type}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['type' => new RawValues('foo')])]
public function listAction(string $type)
{
}
```

In this example, the route parameter `type` is explicitly set to the value `foo`.

### Using Enum Values

If you have a predefined set of values in a backed enum, you can map route parameters to those enum values:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;

#[Route('/posts/{type}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['type' => new EnumValues(TypeEnum::class)])]
public function listAction(string $type)
{
}
```

Here, multiple URLs are generated based on all values defined in the `TypeEnum` class for the `type` parameter.

### Combining Multiple Values

You can combine multiple sources of values, such as enums and raw values, using `CompoundValues`:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;

#[Route('/posts/{lang}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['lang' => new CompoundValues(
    new EnumValues(LanguageCodes::class),
    new RawValues('XK'), // Kosovo code
)])]
public function listAction(string $lang)
{
}
```

In this example, multiple URLs are generated based on all values from the `LanguageCodes` enum and the raw value `XK`
for the `lang` parameter.

### Using Values Provided by an Expression

You can also map route parameters to values provided dynamically using a **Symfony ExpressionLanguage** expression.
This is useful when a route parameter cannot be mapped directly to a single property and needs to be composed or
transformed.

In these expressions, the entity is available as the `obj` variable:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;

#[Route('/posts-by-author/{full_name}', name: 'posts_list_by_author', methods: 'GET')]
#[PurgeOn(Author::class, routeParams: ['full_name' => new ExpressionValues('obj.firstName~"-"~obj.lastName')])]
public function listAction(Author $author)
{
}
```

You can also add [custom Expression Language functions](custom-expression-language-functions.md) to extend the available
expression syntax.

### Using Values Provided by a Service or Static Method

As an alternative to expressions, route parameter values can be provided dynamically by a service or a static method.
This is particularly useful when you need route parameters that depend on context or runtime information:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

#[Route('/posts/{type}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['type' => new DynamicValues('my_service')])]
public function listAction()
{
}
```

By default, the entire entity being purged is passed to the route parameter provider. If your provider only needs a
specific part of the entity, you can limit what is passed by providing a second argument to `DynamicValues`.

This argument is a **Symfony PropertyAccess property path** and will be resolved against the entity before being passed
to the provider:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

#[Route('/posts/{type}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['type' => new DynamicValues('my_service', 'property')])]
public function listAction()
{
}
```

To make a service available for resolving route parameter values, ensure it is tagged correctly in the service
configuration:

```yaml
# services.yaml
App\MyService:
    tags:
        - { name: 'purgatory.route_parameter_service', alias: my_service }
```

Alternatively, you can use the [`#[AsRouteParamService]`][0] attribute directly in the service class:

```php
use Sofascore\PurgatoryBundle\Attribute\AsRouteParamService;

#[AsRouteParamService('my_service')]
class MyService
{
    public function __invoke(Post $post)
    {
        // Return the desired value for the route parameter
    }
}
```

You can also reference a static method directly instead of a service:

```php
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

#[Route('/posts/{type}', name: 'posts_list', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['type' => new DynamicValues([MyClass::class, 'getValue'])])]
public function listAction()
{
}

final class MyClass
{
    public static function getValue(Post $post)
    {
        // Return the desired value for the route parameter
    }
}
```

[0]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Attribute/AsRouteParamService.php
