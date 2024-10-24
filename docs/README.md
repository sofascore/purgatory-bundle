# Getting Started

The bundle is designed to automatically generate and send cache purge requests to HTTP cache backends such as Symfony's
HTTP cache or Varnish. It leverages Doctrine events to detect changes in entities and generates URLs that need to be
purged based on configured routes.

## Why URL-based Invalidation?

This bundle uses URL-based invalidation instead of tag-based invalidation due to the following reasons:

1. **Performance Concerns**: Varnish's tag-based invalidation can lead to slow responses when multiple URLs are
   invalidated simultaneously.
1. **Header Size Limitations**: Tags are typically passed through HTTP headers, which have size limitations. This means
   not all tags may fit within the header limits.
1. **Cost Implications**: Some CDN providers charge extra for tag-based invalidation, making URL-based purging a more
   cost-effective solution.

## Supported Backends

The bundle includes built-in support for [Symfony HTTP Cache](https://symfony.com/doc/current/http_cache.html) and a
basic [Varnish](https://varnish-cache.org/) implementation. Each backend is realized by implementing
the [`PurgerInterface`](/src/Purger/PurgerInterface.php).

It also comes with a `void`, which can be used during development when cache purging is not required. The `void` simply
ignores all purge requests, making it ideal for non-production environments. Additionally, an `in-memory` is provided,
designed specifically for testing purposes. The `in-memory` simulates purging actions without interacting with external
cache services, enabling you to verify your purging logic in tests.

For advanced use cases, you can create custom purgers by implementing the `PurgerInterface`. This allows you to
integrate with any custom or third-party HTTP cache backend that fits your project requirements.

### Configuring Symfony's HTTP Cache

Configure Symfony's HTTP Cache following
the [official documentation](https://symfony.com/doc/current/http_cache.html#symfony-reverse-proxy).

Enable the Symfony purger with the following configuration:

```yaml
purgatory:
    purger: symfony
```

### Configuring Varnish Cache

To enable Varnish to support PURGE requests, add the following example configuration to your VCL file. You may need to
customize it based on your specific Varnish setup:

```vcl
acl purge {
    "localhost";
    "172.16.0.0"/12; # Common Docker IP range, adjust as needed
    # Add more whitelisted IPs here
}

sub vcl_recv {
    if (req.method == "PURGE") {
        if (client.ip !~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (purge);
    }
}
```

Enable the Varnish purger with the following configuration:

```yaml
purgatory:
    purger: varnish
```

Optionally, you can specify a list of Varnish hosts:

```yaml
purgatory:
    purger:
        name: varnish
        hosts:
            - varnish1.example.com
            - varnish2.example.com
            - varnish3.example.com
```

If no hosts are specified, the bundle will use the host from the URL.

## How It Works

The bundle listens to **Doctrine** lifecycle events (`postUpdate`, `postRemove`, `postPersist`) to automatically detect
when entities are modified, created, or deleted. When these changes are flushed to the database, the bundle steps in to
process them.

During this process, the bundle identifies a list of purge subscriptions associated with the affected entities and their
properties. It uses these subscriptions to determine which URLs need to be purged. The URLs are generated based on
predefined routes and mapping rules that link specific entities and their properties to the corresponding routes.

Once the relevant URLs are determined, the bundle sends these purge requests to the configured purger, which then clears
the cached content for those URLs.

## Configuring Purge Subscriptions

Purge subscriptions can be configured using the [`#[PurgeOn]`](/src/Attribute/PurgeOn.php) attribute.

### Basic Example

In this example, the post details page is purged whenever any change is made to the `Post` entity:

```php
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Symfony\Component\Routing\Attribute\Route;

class PostController
{
    #[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
    #[PurgeOn(Post::class)]
    public function detailsAction(Post $post)
    {
    }
}
```

Here, the `id` property is automatically mapped to the route parameter with the same name.

### Explicit Mapping of Route Parameters

If the parameter names differ, you can explicitly map them:

```php
#[Route('/post/{postId<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Post::class, routeParams: ['postId' => 'id'])]
public function detailsAction(Post $post)
{
}
```

### Targeting Specific Properties

By default, all properties are subscribed to purging. You can customize this by specifying which properties to watch:

```php
#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Post::class, target: ['title', 'text'])]
public function detailsAction(Post $post)
{
}
```

In this example, the purge will only occur if the `title` or `text` properties change.

### Using Serialization Groups

You can also specify which Symfony
Serializer [serialization groups](https://symfony.com/doc/current/serializer.html#using-serialization-groups-attributes)
to use:

```php
#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Post::class, target: new ForGroups('common'))]
public function detailsAction(Post $post)
{
}
```

Now, the purge will occur for all properties that are part of the `common` serialization group.

### Adding Conditional Logic with Expression Language

Symfony's Expression Language component can be used to add conditions that must be met for the purge to occur. In these
expressions, the entity is available as the `obj` variable:

```php
#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Post::class, if: 'obj.upvotes > 3000')]
public function detailsAction(Post $post)
{
}
```

In this example, the purge will only occur if the post has more than 3,000 upvotes.

### Limiting Purge to Specific Routes

By default, the attribute generates URLs for all routes associated with the action. You can limit this to one or more
specific routes:

```php
#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[Route('/blog-post/{id<\d+>}', name: 'post_details_old', methods: 'GET')]
#[PurgeOn(Post::class, route: ['post_details', 'post_details_old'])]
public function detailsAction(Post $post)
{
}
```

In this example, only the `post_details` and `post_details_old` routes will be purged.

### Limiting by Action Type

You can also limit the purging to a specific action as defined in the [`Action`](/src/Listener/Enum/Action.php) enum:

```php
use Sofascore\PurgatoryBundle\Listener\Enum\Action;

#[Route('/post/{id<\d+>}', name: 'post_details', methods: 'GET')]
#[PurgeOn(Post::class, action: Action::Update)]
public function detailsAction(Post $post)
{
}
```

Now, the purge will only occur when the entity is updated, but not when it is created or deleted.
