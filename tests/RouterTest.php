<?php

use Guide42\Traba\Router;

class RouterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage Route not found
     */
    public function testMatchRouteNotFound()
    {
        $router = new Router(array());
        $router->addRoute('view_home', null, '');
        $router->match(array('user', '123'));
    }

    public function testMatch()
    {
        $root = array(
            'u' => new UserLocator(),
        );

        $router = new Router($root);
        $router->addRoute('view_user', 'User', '');
        $router->addRoute('view_user', 'User', 'edit');

        list($route, $resource) = $router->match(
            array_filter(explode('/', '/u/123'))
        );

        $this->assertInstanceOf('User', $resource);
        $this->assertEquals('John Doe', $resource->name);
        $this->assertEquals('view_user', $route);
    }

    public function testMatchRequest()
    {
        $request = new Request('GET', 'http://localhost/user/123/edit');

        $root = array(
            'http:' => array(
                'localhost' => array(
                    'user' => new UserLocator(),
                ),
            ),
        );

        $router = new Router($root);
        $router->addRoute('edit_user', 'User', 'edit');

        list($route, $resource) = $router->matchRequest($request);

        $this->assertInstanceOf('User', $resource);
        $this->assertEquals('John Doe', $resource->name);
        $this->assertEquals('edit_user', $route);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Could not find URI in the request
     */
    public function testMatchWithInvalidRequest()
    {
        $router = new Router(array());
        $router->matchRequest(array());
    }

    public function testMatchWithoutResource()
    {
        $router = new Router(array());
        $router->addRoute('view_home', null, '');
        $router->addRoute('view_news', null, 'news');

        list($rhome, $_) = $router->matchRequest(new Request('GET', '/'));
        list($rnews, $_) = $router->matchRequest(new Request('GET', '/news'));

        $this->assertEquals('view_home', $rhome);
        $this->assertEquals('view_news', $rnews);
    }

    public function testMatchRequestWithPrefix()
    {
        $router = new Router(array());
        $router->addRoute('view_home', null, '');
        $router->addRoute('view_news', null, 'news');

        $prefix = '/example.php';

        $reqhome = new Request('GET', '/example.php/');
        $reqnews = new Request('GET', '/example.php/news');

        list($rhome, $_) = $router->matchRequest($reqhome, $prefix);
        list($rnews, $_) = $router->matchRequest($reqnews, $prefix);

        $this->assertEquals('view_home', $rhome);
        $this->assertEquals('view_news', $rnews);
    }

    public function testAssemble()
    {
        $root = new Resource(array(
            'u' => new UserLocator(),
        ));

        $segments = array('u', '123');

        $router = new Router($root);
        $router->addRoute('view_user', 'User', '');
        $router->addRoute('view_user', 'User', 'edit');

        list($route, $resource) = $router->match($segments);

        $this->assertEquals($segments, $router->assemble($resource));
    }

    public function testAssembleExtra()
    {
        $root = new Resource(array(
            'u' => new UserLocator(),
        ));

        $segments = array('u', '123', 'edit');

        $router = new Router($root);
        $router->addRoute('view_user', 'User', '');
        $router->addRoute('view_user', 'User', 'edit');

        list($route, $resource) = $router->match($segments);

        $this->assertEquals($segments, $router->assemble($resource, 'edit'));
    }
}

### FIXTURES ##################################################################

class Request {
    public $method;
    public $uri;
    public function __construct($method, $uri) {
        $this->method = $method;
        $this->uri = $uri;
    }

    public function getMethod() {
        return $this->method;
    }

    public function getUri() {
        return $this->uri;
    }
}

class User {
    public $__name = '';
    public $__parent = null;

    public $name;
    public $age;
    public function __construct($name, $age) {
        $this->name = $name;
        $this->age = $age;
    }
}

class UserLocator implements \ArrayAccess {
    public $__name = '';
    public $__parent = null;

    public $users;

    public function __construct() {
        $this->users = array(
            123 => new User('John Doe', 42),
        );
    }

    public function offsetExists($offset) {
        return isset($this->users[$offset]);
    }

    public function offsetGet($offset) {
        $user = $this->users[$offset];
        $user->__parent = $this;
        $user->__name = $offset;

        return $user;
    }

    public function offsetSet($offset, $value) {}

    public function offsetUnset($offset) {}
}

class Resource extends \ArrayObject {
    public $__name = '';
    public $__parent = null;

    public function offsetGet($index)
    {
        $resource = parent::offsetGet($index);
        $resource->__parent = $this;
        $resource->__name = $index;

        return $resource;
    }
}