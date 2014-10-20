Difference between URL and Traversal
====================================

Let's see an example. Imagine we have a photo gallery, and we want that when
a pattern `/{username}/photos/{photo_id}` is matched, some controller will be
called.

We could think of our models here:

```php
class User
{
    public $username;
    public $name;

    public function __construct($username, $name)
    {
        $this->username = $username;
        $this->name = $name;
    }
}

class UserPhoto
{
    public $id;
    public $user; /** @var User */
    public $src;

    public function __construct($id, $user, $src)
    {
        $this->id = $id;
        $this->user = $user;
        $this->src = $src;
    }
}
```

And our interface with the database (in this case, hardcoded arrays):

```php
function find_user($username)
{
    static $users = array(
        'john' => array('name' => 'John'),
        'fabpot' => array('name' => 'Fabien'),
    );

    if (isset($users[$username]) {
        return new User($username, $users[$username]['name']);
    }

    throw new ResourceNotFoundException("User $username not found");
}

function find_photo($id, User $user)
{
    static $photos = array(
        '1' => array('username' => 'john', 'src' => 'images/john.jpg'),
        '123' => array('username' => 'fabpot', 'src' => 'images/fabpot.jpg'),
    );

    if (isset($photos[$id]) && $photos[$id]['username'] === $user->username) {
        return new UserPhoto($id, $user, $photos[$id]['src']);
    }

    throw new ResourceNotFoundException("Photo $id not found for {$user->username}");
}
```

Dispatching with Symfony
------------------------

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
function view_photo($username, $photo_id)
{
    $user = find_user($username);
    $photo = find_photo($photo_id, $user);

    // [...]
}
```

We see that our controller is responsible to transform the given username and
photo ID from string to the domain model. This gives all responsibility to the
controller that must know how to find models in the database (for example) and
then how to use them to generate the response.

> **NOTE**: In Symfony Framework, this can be "fixed" using
> [`@ParamConverter`][1] included in the `SensioFrameworkExtraBundle`.
>
> This need annotations, because of that it need to be cached and require a lot
> of external libraries (such `doctrine/annotations`). This is *ok* in a big
> framework like Symfony, but if we want to separate responsibility in our
> controllers in an simple way, there is no other option.

[1]: http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html

Dispatching with Traba
----------------------

The same example is little bit longer, but it has separation of responsibility.

We need to think about our resources. In the root our tree there should be
some way of locate for users. We already have the `find_user` function, but it
doesn't behave like an array, so let's wrap it:

```php
class UserLocator implements ArrayAccess
{
    public function offsetExists($offset)
    {
        try {
            find_user($offset);
        } catch (ResourceNotFoundException $e) {
            return false;
        }
        return true;
    }

    public function offsetGet($offset)
    {
        return find_user($offset);
    }

    public function offsetSet($offset, $value) {}
    public function offsetUnset($offset) {}
}
```

Now our `$root = new UserLocator` will return an instance of `User` when asked
by key for the username. But it will end there, because a `User` is not an
array-like. So we should modify our model to make behave like a resource.

```diff
-class User
+class User implements ArrayAccess
 {
     public $username;
     public $name;

     public function __construct($username, $name)
     {
         $this->username = $username;
         $this->name = $name;
     }
+
+    public function offsetExists($offset)
+    {
+        return $offset === 'photos';
+    }
+
+    public function offsetGet($offset)
+    {
+        if ($offset === 'photos') {
+            return new UserPhotoLocator($this);
+        }
+
+        throw new ResourceNotFoundException();
+    }
+
+    public function offsetSet($offset, $value) {}
+    public function offsetUnset($offset) {}
 }
```

And now when `$root['fabpot']['photos']` is request and instance of the class
`UserPhotoLocator` will be returned. This class is very similar to
`UserLocator` but will use `find_photo` with instead of `find_user`.

Our tree should be finished.

What is left is the same as in the Symfony example. Just create the router,
declare the route, match and dispatch.

```php
use Guide42\Traba\Router;

$router = new Router(new UserLocator());
$router->addRoute('view_photo', 'UserPhoto', '');

list($controller, $resource) = $router->match(
    array_filter(explode('/', '/fabpot/photos/123'))
);

try {
    call_user_func($controller, $resource);
} catch (ResourceNotFoundException $e) {
    header("HTTP/1.0 404 Not Found");
    die("404 Not Found");
}
```

But the big difference is that our controller should accept a `UserPhoto` as
parameter and will have no logic of looking for it.

```php
function view_photo(UserPhoto $photo)
{
    // [...]
}
```

That's how traversal router works.
