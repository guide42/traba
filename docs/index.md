Traba, the Traversal Router
===========================

Traba is a traversal router. It is licensed under a ISC license.

The simplest example:

```php
use Guide42\Traba\Router;

$router = new Router(array());
$router->addRoute('view_home', null, '');

list($controller, $resource) = $router->match(array());
```

Documentation
-------------

- [Introduction](introduction.md)
- [Comparison](comparison.md)
