# Speeding Up Tests

Even with the `in-memory` purger, every flush generates purge requests: the purge subscriptions of the changed entities
are evaluated, the route providers are called and the URLs are generated. Most tests never assert on these purges, so
in a large test suite this work can take up a noticeable part of the runtime. There are two ways to avoid it, depending
on whether speed or catching errors in the purge configuration matters more.

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

### Enabling Purging for Specific Tests

The bundle's PHPUnit extension enables purging for specific tests. First enable the `test` option, which replaces the
switcher with an implementation whose state can be overridden globally:

```yaml
# config/packages/purgatory.yaml
when@test:
    purgatory:
        purger: in-memory
        purge_on_entity_change: false
        test: true
```

Then register the extension, which requires PHPUnit 10 or higher:

```xml
<!-- phpunit.xml -->
<extensions>
    <bootstrap class="Sofascore\PurgatoryBundle\Test\PHPUnit\PurgatoryExtension" />
</extensions>
```

Purging can now be enabled only where it is needed with the [`#[WithEntityChangePurging]`][2] attribute. When placed on
a test class, purging is enabled for all of its tests, from `setUpBeforeClass()` until after `tearDownAfterClass()`.
When placed on a test method, purging is enabled for that test only, from before `setUp()` until after `tearDown()`.
In both cases the previous state is restored afterwards, even if a test errors, fails or is skipped:

```php
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Test\PHPUnit\WithEntityChangePurging;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PurgeTest extends KernelTestCase
{
    use InteractsWithPurgatory;

    #[WithEntityChangePurging]
    public function testPurgePost()
    {
        // ...
    }
}
```

The override applies to every kernel while it is set, including the ones a test client reboots between requests, and
isn't cleared when the services are reset. It can also be set manually with the static `enableGlobally()`,
`disableGlobally()` and `resetGlobally()` methods of the [`TestEntityChangePurgeSwitcher`][1] class. The methods of
the switcher service, such as `whileDisabled()`, still take precedence over it.

### Enabling Purging Manually

Without the extension, or to enable purging for only a part of a test, wrap the changes in the `whileEnabled()` method
of the [`EntityChangePurgeSwitcherInterface`][0] service:

```php
$entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
$switcher = self::getContainer()->get(EntityChangePurgeSwitcherInterface::class);

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

Asserting that nothing was purged would always pass while purging is disabled, so `assertNoUrlsArePurged()` and
`assertUrlIsNotPurged()` throw an exception in that case. Make these assertions inside the callback:

```php
$switcher->whileEnabled(static function () use ($post, $entityManager): void {
    $post->views = 100;

    $entityManager->flush();

    self::assertNoUrlsArePurged();
});
```

A test client reboots the kernel before each request after the first one, which creates a new switcher. To keep
purging enabled across several requests, call `$client->disableReboot()` and make the requests inside the callback:

```php
$client = static::createClient();
$client->disableReboot();

self::getContainer()
    ->get(EntityChangePurgeSwitcherInterface::class)
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
    ->get(EntityChangePurgeSwitcherInterface::class)
    ->whileDisabled(static fn () => flush_after(
        static fn () => PostFactory::createMany(100),
    ));
```

[0]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Listener/EntityChangePurgeSwitcherInterface.php
[1]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Test/TestEntityChangePurgeSwitcher.php
[2]: https://github.com/sofascore/purgatory-bundle/blob/2.x/src/Test/PHPUnit/WithEntityChangePurging.php
