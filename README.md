Purgatory
=========

Purgatory is extension which makes it possible for Symfony applications to handle enormous load using minimal infrastructure. 
Infrastructure meant to be used with this bundle along with Symfony application is a HTTP caching reverse proxy. 

This bundle implements an easy and maintainable way to invalidate cache on an endpoints based on changes in Doctrine entities.

Installation
------------

Prerequisite - doctrine/orm

`composer require sofascore/purgatory`

Setup - Symfony reverse proxy
-----

Enable Symfony Http Cache component in `config/packages/framework.yaml`

```yaml
framework:
  http_cache: true
```

Wrap the default kernel into HttpCache caching kernel `public/index.php`

```php
<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new HttpCache(new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']));
};
```

Define implementation of `PurgerInterface` and host to purge in `config/packages/purgatory`

```yaml
purgatory:
  purger: 'sofascore.purgatory.purger.symfony'
  host: 'localhost:3000'
```

Usage
-----

Suppose we have a simple entity and controller.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="post1")
 */
class Post
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    public $id;
    /**
     * @ORM\Column(type="string")
     */
    public $title;
    /**
     * @ORM\Column(type="string")
     */
    public $content;
}
```

```php
namespace App\Controller;


use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PostController extends AbstractController
{

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @Route("/post/{id<\d+>}", methods={"GET"})
     */
    public function indexAction(int $id)
    {
        /** @var Post $post */
        $post = $this->entityManager->getRepository(Post::class)->find($id);
        if (null === $post) {
            return new Response(status: 404);
        }

        $r = new Response(json_encode(['title' => $post->title, 'content'=>$post->content]), 200, []);
        $r->setSharedMaxAge(3600);
        $r->setMaxAge(3600);

        return $r;
    }
}
```

When we send request on an endpoint for first time, reverse proxy saves the response and serves the same response until
it expires (1 hour in this example). If the state of an entity changes in the meantime, content on our website stays the
same until cache expires.

Purgatory has an annotation which defines rules for cache invalidation when a state of an object changes.

```php
use SofaScore\Purgatory\Annotation\PurgeOn;

    /**
    * @Route("/post/{postId<\d+>}", methods={"GET"})
    * @PurgeOn(Post::class, parameters={"postId":"id"}, properties={"title", "content"}, if="obj.title !== null")
    */ 
    public function indexAction(int $id) //...
```

PurgeOn annotation
------------------
Parameters:
- **Required** FQCN of an entity whose changes are being tracked for cache purging.
- `parameters` 
    - defines an associative array where keys are route parameters and values are property names.
- `properties` 
    - list of properties which are required to change in order to purge the endpoint.
    - if omitted, change of any property purges the cache
- `if` 
    - an expression which has to be true in order to purge the endpoint with specified parameters.

Workflow
--------
When property of `Post` entity is changed and flushed to database, Purgatory goes through PurgeOn annotations where changed property is in list of properties, checks the `if` expression, injects the parameters and purges the route.

Debugging
---------

Custom Purger
---------
If you have a more complex setup or use varnish (recommended) you should implement your own purger
that will be aware of your infrastructure.

Example purger:
```php
namespace App\Service;


use GuzzleHttp\Client;
use SofaScore\Purgatory\Purger\PurgerInterface;

class VarnishPurger implements PurgerInterface
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function purge(iterable $urls): void
    {
        foreach ($urls as $url) {
            $this->client->request('PURGE', 'http://varnish_host' . $url);
        }
    }
}
```

You must also register that Purger with the configuration:
```yaml
purgatory:
  purger: App\Service\VarnishPurger
```

That's it!

Examples
--------

```php
@PurgeOn()
```
