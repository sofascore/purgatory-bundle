# Custom Purgers

You can create custom purgers by implementing the [`PurgerInterface`][0]. This allows you to define your own logic for
handling purge requests. Once implemented, be sure to tag your custom purger with `purgatory.purger` to make it
available for configuration.

Here's an example of a custom purger for Cloudflare:

```php
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('purgatory.purger', ['alias' => 'cloudflare'])]
class CloudflarePurger implements PurgerInterface
{
    public function purge(iterable $purgeRequests): void
    {
        // Your custom logic to send purge requests to Cloudflare
    }
}
```

## Enabling Your Custom Purger

To enable your custom purger, update your configuration file with the alias you specified:

```yaml
# config/packages/purgatory.yaml
purgatory:
    purger: cloudflare
```

In this example, the alias `cloudflare` is used to refer to the custom purger.

## Passing Context to Your Purger

A purge subscription can carry a `context`, an array the bundle hands to your purger untouched. Use it to tell the
purger how to process the purges a subscription triggers, for example to throttle purges caused by comments but not
by post edits:

```php
#[Route('/post/{id}', 'post_details')]
#[PurgeOn(Post::class)]
#[PurgeOn(Comment::class, routeParams: ['id' => 'post.id'], context: ['throttle' => 8])]
public function detailsAction(Post $post)
{
}
```

Each `PurgeRequest` has a `contexts` list with the context of every subscription that led to its URL. A URL is
purged only once per flush, so when a post is edited and commented on in the same flush, the request above carries
both `[]` and `['throttle' => 8]`. An empty array stands for a subscription without context:

```php
public function purge(iterable $purgeRequests): void
{
    foreach ($purgeRequests as $purgeRequest) {
        // Hold the purge back only if every subscription behind the URL asked for it
        $throttle = min(array_map(
            static fn (array $context): int => $context['throttle'] ?? 0,
            $purgeRequest->contexts,
        ));

        // ...
    }
}
```

Read `$purgeRequest->contexts` rather than `$purgeRequest->route->context`. When several subscriptions lead to the
same URL, the request keeps the route of the first one, so its context is only one of them.

The context ends up in the cached configuration, so it may only contain scalars, enums, nulls and arrays. `INF` and
`NAN` aren't allowed either.

When purges are processed asynchronously, the context travels in the Messenger message. With the Symfony serializer
(`messenger.transport.symfony_serializer`), enums don't reach the purger as enum cases. To support any serializer, put
the enum's value in the context and turn it back into a case in the purger, for example with
`Priority::from($context['priority'])`.

### Sharing Context Across a Controller

To give every subscription of a controller or of an action the same context, add the `#[PurgeContext]` attribute to the
controller class or to the action. It accepts the same values as the `context` of a `PurgeOn`:

```php
use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

#[PurgeContext(['priority' => 'low', 'varnish' => ['tags' => ['social'], 'ttl' => 60]])]
class SocialController
{
    #[Route('/social/feed', 'social_feed')]
    #[PurgeOn(Post::class)]
    public function feedAction()
    {
    }

    #[Route('/social/post/{id}', 'social_post')]
    #[PurgeContext(['varnish' => ['tags' => ['social', 'post'], 'ttl' => 5]])]
    #[PurgeOn(Post::class)]
    #[PurgeOn(Comment::class, routeParams: ['id' => 'post.id'], context: ['priority' => 'high'])]
    public function postAction(Post $post)
    {
    }

    #[Route('/social/live', 'social_live')]
    #[PurgeContext(['varnish' => null])]
    #[PurgeOn(Post::class)]
    public function liveAction()
    {
    }
}
```

The context of a subscription starts as the context of the class. The context of the action replaces the top-level keys
it sets, and then the `context` of the `PurgeOn` does the same. A key replaces the whole value, so nested arrays aren't
merged: the action above repeats the `social` tag to keep it. To remove a value, set its key to `null`.

So the purger receives these contexts:

| Route         | Entity    | Context                                                                           |
|---------------|-----------|-----------------------------------------------------------------------------------|
| `social_feed` | `Post`    | `['priority' => 'low', 'varnish' => ['tags' => ['social'], 'ttl' => 60]]`         |
| `social_post` | `Post`    | `['priority' => 'low', 'varnish' => ['tags' => ['social', 'post'], 'ttl' => 5]]`  |
| `social_post` | `Comment` | `['priority' => 'high', 'varnish' => ['tags' => ['social', 'post'], 'ttl' => 5]]` |
| `social_live` | `Post`    | `['priority' => 'low', 'varnish' => null]`                                        |

The context of an action also applies to the `PurgeOn` attributes of its controller class, and the order of the
attributes doesn't matter. The `#[PurgeContext]` attribute doesn't apply to subscriptions defined in YAML.

[0]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Purger/PurgerInterface.php
