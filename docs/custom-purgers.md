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

When purges are processed asynchronously, the context travels in the Messenger message. The default PHP serializer keeps
it as it is, but with the Symfony serializer (`messenger.transport.symfony_serializer`) enums reach the purger as plain
values rather than enum cases. Keep the default serializer if your purger compares enums.

[0]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Purger/PurgerInterface.php
