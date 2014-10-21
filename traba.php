<?php

namespace Guide42\Traba;

interface RouterInterface
{
    /**
     * Traverse the given path through the tree and then match the result
     * with the list of routes.
     *
     * @param array $segments path to route
     *
     * @throws \RuntimeException
     * @return array
     */
    function match(array $segments);

    /**
     * Inverse of match, transforms a resource into segments.
     *
     * The given resource and, it whole lineage need to be location-aware for
     * the assemble to work.
     *
     * @param object $resource
     */
    function assemble($resource);
}

class Router implements RouterInterface
{
    protected $root;
    protected $traverser;
    protected $matcher;
    protected $assembler;
    protected $routes;

    public function __construct($root,
        callable $traverser=null,
        callable $matcher=null,
        callable $assembler=null
    ) {
        if ($traverser === null) {
            $traverser = __NAMESPACE__ . '\\traverser';
        }

        if ($matcher === null) {
            $matcher = __NAMESPACE__ . '\\matcher';
        }

        if ($assembler === null) {
            $assembler = __NAMESPACE__ . '\\assembler';
        }

        $this->root = $root;
        $this->traverser = $traverser;
        $this->matcher = $matcher;
        $this->assembler = $assembler;
        $this->routes = [];
    }

    public function addRoute($handler, $resource, $name) {
        $this->routes[] = [$handler, $resource, $name];
    }

    public function match(array $segments)
    {
        $node = call_user_func($this->traverser, $this->root, $segments);
        $route = call_user_func($this->matcher, $this->routes,
            $node['resource'], $node['name']);

        if ($route === null) {
            throw new \RuntimeException('Route not found');
        }

        $resource = $node['resource'];

        if (is_object($resource) &&
            property_exists($resource, '__parent') &&
            property_exists($resource, '__name')
        ) {
            $resource->__parent = $node['last'][0];
            $resource->__name = $node['last'][1];
        }

        return [$route, $node['resource']];
    }

    public function matchRequest($request, $prefix='')
    {
        if (method_exists($request, 'getUri')) {
            $uri = (string) $request->getUri();
        } else {
            throw new \InvalidArgumentException(
                'Could not find URI in the request'
            );
        }

        if (!empty($prefix) && strpos($uri, $prefix) === 0) {
            $uri = substr($uri, strlen($prefix));
        }

        return $this->match(array_filter(explode('/', $uri)));
    }

    public function assemble($resource, $extra=null)
    {
        $segments = call_user_func($this->assembler, $resource);

        if ($extra !== null) {
            $segments = array_merge($segments, array_values((array) $extra));
        }

        return $segments;
    }
}

function traverser($root, array $segments)
{
    $last = array(null, '');

    foreach($segments as $i => $segment) {
        if (!is_array($root) &&
            !$root instanceof \ArrayAccess ||
            !isset($root[$segment])
        ) {
            return array(
                'last'      => $last,
                'resource'  => $root,
                'name'      => $segment,
                'traversed' => array_slice($segments, 0, $i),
                'after'     => array_slice($segments, $i + 1),
            );
        }

        $last = array($root, $segment);
        $root = $root[$segment];
    }

    return array(
        'last'      => $last,
        'resource'  => $root,
        'name'      => '',
        'traversed' => $segments,
        'after'     => [],
    );
}

function matcher($routes, $resource, $name)
{
    foreach ($routes as $route) {
        if ($route[2] === $name &&
            ($route[1] === null ||
            $resource instanceof $route[1])
        ) {
            return $route[0];
        }
    }

    return null;
}

function assembler($resource)
{
    $parts = array();

    while ($resource !== null) {
        if (!is_object($resource) ||
            !property_exists($resource, '__parent') ||
            !property_exists($resource, '__name')
        ) {
            throw new \RuntimeException(
                'The assembler needs a full tree of location aware resources'
            );
        }

        $parts[] = $resource->__name;
        $resource = $resource->__parent;
    }

    return array_values(array_filter(array_reverse($parts)));
}

class Resource extends \ArrayObject
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

    public function offsetSet($name, $resource)
    {
        if (!$resource instanceof Resource) {
            throw new \InvalidArgumentException('You must provide a resource');
        }

        parent::offsetSet($name, $resource);
    }
}

class ResourceMapping extends Resource
{
    private $static = array();
    private $dynamic = array();
    private $dynbname = array();
    private $resources = array();

    public function offsetExists($name)
    {
        if (parent::offsetExists($name)) {
            return true;
        }

        if (isset($this->resources[$name])) {
            return true;
        }

        if (isset($this->static[$name])) {
            return true;
        }

        foreach ($this->dynamic as $data) {
            list($matcher, $fn) = $data;

            try {
                $resource = call_user_func($matcher, $name);

                if ($resource === null) {
                    continue;
                }
            } catch (\OutOfBoundsException $e) {
                continue;
            }

            $ret = call_user_func($fn, $resource);

            if ($ret instanceof Resource) {
                $resource = $ret;
            }

            $this->resources[$name] = $resource;

            return true;
        }

        return false;
    }

    public function offsetGet($name)
    {
        if (parent::offsetExists($name)) {
            return parent::offsetGet($name);
        }

        $resource = null;

        if (isset($this->static[$name])) {
            $resource = $this->static[$name];
        }

        elseif (isset($this->resources[$name])) {
            $resource = $this->resources[$name];
        }

        if ($resource === null) {
            throw new \OutOfBoundsException();
        }

        $resource->__parent = $this;
        $resource->__name = $name;

        return $resource;
    }

    public function set($matcher, \Closure $fn)
    {
        if ($matcher instanceof \Closure) {
            $this->dynamic[] = array($matcher, $fn);
        } else {
            $this->static[$matcher] = $fn;
        }
    }
}