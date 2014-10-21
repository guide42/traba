Introduction
============

Traba is a traversal router. Which is just an alternative to common pattern
matching to route an URL to a resource.

URL matching
------------

The most common way of routing is by comparing the given URL to a set of
registered "patterns" (a.k.a. regular expressions). Each of them has some
metadata information such as the *controller* to call when the route match
with the request URL. If none of the "patterns" matched, an exception will be
raised that then is converted to a *404 Not Found* response.

Traversal
---------

Traversal algorithm is very similar how the filesystem works but lookup
resources in a tree. This tree behaves just like an array that will return
a child resource for a given key.

For example, you want to match the path `/artists/sumo`, the traverser will
do `$root['artists']['sumo']` and treat the returning values as a resource
that could be injected into your controller. If along the way, a section of
the tree is not an array-like or doesn't have the expected key, the algorithm
will stop there.

Resource?
---------

A resource is a section of your tree. There are two types of resources.

First, a resource, could be anything that resembles an array, os just an array
it self. The resource will have the responsibility of knowing it's children
so when traverser ask for a specific one, it can respond.

Secondly, a resource can be *location-aware*. This means that individually
knows who is its parent and how it call it. Parent and name are provided by
public properties called `__parent` and `__name` respectively.

For example:

```php
class Resource extends ArrayObject
{
    public $__parent = null;
    public $__name = '';

    public function offsetGet($name)
    {
        $resource = parent::offsetGet($name);
        $resource->__parent = $this;
        $resource->__name = $name;

        return $resource;
    }
}
```

Exactly this class is available as `\Guide42\Traba\Resource`.
