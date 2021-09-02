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
/**
 * @Route("/post")
 */
class PostController extends AbstractController
{

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @Route("/{postId<\d+>}", methods={"GET"})
     */
    public function detailsAction(int $postId)
    {
        /** @var Post $post */
        $post = $this->entityManager->getRepository(Post::class)->find($postId);
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
    * @Route("/{postId<\d+>}", methods={"GET"})
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

Add purge capability to varnish
```
acl purge {
        "localhost";
        "172.0.0.0"/8; # if behind docker
        # add more whitelisted ips here
}

sub vcl_recv {
        if (req.method == "PURGE") {
                if (!client.ip ~ purge) {
                        return(synth(405,"Not allowed."));
                }
                return (purge);
        }
}
```
That's it!

Examples
--------
Endpoint which fetches all properties of a single post.

Use `PurgeOn` with FQCN and map route parameters with property of an entity.
On change of any property of a post, endpoint with entity id injected as route parameter `postId` gets invalidated. 
```php
    /**
     * @Route("/{postId<\d+>}", methods={"GET"})
     * @PurgeOn(Post::class, parameters={"postId":"id"})
     */
    public function detailsAction(int $postId) {
```
Endpoint which fetches all featured Posts.

Use `PurgeOn` and specify a single property - cache invalidation happens every time when property `featured` changes on any of the posts.

```php
    /**
     * @Route("/featured", methods={"GET"})
     * @PurgeOn(Post::class, properties={"featured"})
     */
    public function featuredAction() {
```
Endpoint which fetches a list of all popular posts with more than 3000 upvotes.

Use `PurgeOn` with a condition - cache invalidation happens every time when any of the properties on a Post with more than 3000 upvotes changes.  
```php
    /**
     * @Route("/popular", methods={"GET"})
     * @PurgeOn(Post::class, if="obj.upvotes > 3000")
     */
    public function popularAction(int $postId) {
```

Debugging
--------- 
```bash
php bin/console purgatory:debug Post
```
Purgatory debug command groups all defined purging rules and dumps it on the screen. 
Its argument is an entity name or entity and property
```bash
php bin/console purgatory:debug Post::upvotes
```
Command with defined entity and property dumps all routes which get refreshed by change of that property.
```
\App\Entity\Post
	app_post_details
		path: /post/{postId}
		parameters:
			postId: id

\App\Entity\Post::upvotes
	app_post_popular
		path: /post/popular
		if: obj.upvotes > 3000
```
Observe that change of upvotes causes a cache invalidation on popular posts route as well as on post details route. 
