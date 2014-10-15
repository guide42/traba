Difference between URL and Traversal matching
=============================================

Let's see an example. Imagine we have a photo gallery, and we want that when
a pattern `/{username}/photos/{photo_id}` is matched, some controller will
be called.

For example, in Symfony we will have to declare the route like this:

```php
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;

$route = new Route('/{username}/photos/{photo_id}', array(
  '_controller' => 'view_photo',
));

$routes = new RouteCollection();
$routes->add('view_photo', $route);

$matcher = new UrlMatcher($routes, new RequestContext);
$parameters = $matcher->match('/fabpot/photos/123');
```

Now our `$parameters` will contain all the information we need to dispatch it.
Normally we will use some dispatcher, but for the sake of simplification let
assume that the controller is a callable.

```php
$controller = $parameters['_controller'];

unset($parameters['_route']);
unset($parameters['_controller']);

try {
    call_user_func_array($controller, $parameters);
} catch (ResourceNotFoundException $e) {
    header("HTTP/1.0 404 Not Found");
    die("404 Not Found");
}
```

Finally our controller must look something like this:

```php
function view_photo($username, $photo_id) {
    $user = find_user_by_username_or_throw_404($username);
    $photo = find_photo_by_id_or_throw_404($photo_id);

    // [...]
}
```

We see that our controller is responsible to transform the given username and
photo ID from string to the domain model.

> **NOTE**: In Symfony Framework, this can be "fixed" using
> [`@ParamConverter`][1] included in the `SensioFrameworkExtraBundle`.

[1]: http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html