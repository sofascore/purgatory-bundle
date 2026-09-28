# Speeding Up Tests

Even with the `in-memory` purger, every flush generates purge requests: the purge subscriptions of the changed entities
are evaluated, the route providers are called and the URLs are generated. Most tests never assert on these purges, so
in a large test suite this work can take up a noticeable part of the runtime. There are two ways to avoid it.

## Disabling Purging in Tests

The fastest option is to disable purging in the test environment altogether:

```yaml
# config/packages/purgatory.yaml
when@test:
    purgatory:
        purger: in-memory
        purge_on_entity_change: false
```

Keep in mind that errors in the purge configuration then only surface in the tests that enable purging.

To check purges in a test, wrap the changes in the `whileEnabled()` method of the [`EntityChangePurgeSwitcher`][0]
service:

```php
$entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
$switcher = self::getContainer()->get(EntityChangePurgeSwitcher::class);

// purging is disabled, so creating the post doesn't purge anything
$post = new Post();
$post->title = 'Title';

$entityManager->persist($post);
$entityManager->flush();

$switcher->whileEnabled(static function () use ($post, $entityManager): void {
    $post->title = 'Title New';

    $entityManager->flush();
});

self::assertUrlIsPurged('/post/title');
self::assertUrlIsPurged('/post/title-new');
```

A test client reboots the kernel before each request after the first one, which creates a new switcher. To keep
purging enabled across several requests, call `$client->disableReboot()` and make the requests inside the callback:

```php
$client = static::createClient();
$client->disableReboot();

self::getContainer()
    ->get(EntityChangePurgeSwitcher::class)
    ->whileEnabled(static function () use ($client): void {
        $client->request('POST', '/posts', ['title' => 'Title']);
        $client->request('PATCH', '/post/title', ['title' => 'Title New']);
    });

self::assertUrlIsPurged('/post/title');
self::assertUrlIsPurged('/post/title-new');
```

## Skipping Purges for Fixtures

Fixtures usually make up most of what a test suite flushes, so skipping purges only for them removes the bulk of the
cost. Everything the application does is still purged, so your tests keep catching errors in the purge configuration,
such as an expression that throws.

To skip them, create the fixtures inside the `whileDisabled()` method of the switcher. The flush must happen inside the
callback, which also works with [Foundry](https://github.com/zenstruck/foundry)'s `flush_after()`:

```php
$posts = self::getContainer()
    ->get(EntityChangePurgeSwitcher::class)
    ->whileDisabled(static fn () => flush_after(
        static fn () => PostFactory::createMany(100),
    ));
```

[0]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Listener/EntityChangePurgeSwitcher.php
