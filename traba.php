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

    public function assemble($resource)
    {
        return call_user_func($this->assembler, $resource);
    }
}

function traverser($root, array $segments)
{
    $last = $root;

    foreach($segments as $i => $segment) {
        if (!is_array($root) &&
            !$root instanceof \ArrayAccess ||
            !isset($root[$segment])
        ) {
            return array(
                'parent'    => $last,
                'resource'  => $root,
                'name'      => $segment,
                'traversed' => array_slice($segments, 0, $i),
                'after'     => array_slice($segments, $i + 1),
            );
        }

        $last = $root;
        $root = $root[$segment];
    }

    return array(
        'parent'    => null,
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