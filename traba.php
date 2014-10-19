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
}

class Router implements RouterInterface
{
    protected $root;
    protected $traverser;
    protected $matcher;
    protected $routes;

    public function __construct($root,
        callable $traverser=null,
        callable $matcher=null
    ) {
        if ($traverser === null) {
            $traverser = __NAMESPACE__ . '\\traverser';
        }

        if ($matcher === null) {
            $matcher = __NAMESPACE__ . '\\matcher';
        }

        $this->root = $root;
        $this->traverser = $traverser;
        $this->matcher = $matcher;
        $this->routes = [];
    }

    public function addRoute($handler, $context, $name) {
        $this->routes[] = [$handler, $context, $name];
    }

    public function match(array $segments)
    {
        $node = call_user_func($this->traverser, $this->root, $segments);
        $route = call_user_func($this->matcher, $this->routes,
            $node['context'], $node['name']);

        if ($route === null) {
            throw new \RuntimeException('Route not found');
        }

        return [$route, $node['context']];
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

        if (strpos($uri, $prefix) === 0) {
            $uri = substr($uri, strlen($prefix));
        }

        return $this->match(array_filter(explode('/', $uri)));
    }
}

function traverser($root, array $segments)
{
    foreach($segments as $i => $segment) {
        if (!is_array($root) &&
            !$root instanceof \ArrayAccess ||
            !isset($root[$segment])
        ) {
            return array(
                'context'   => $root,
                'name'      => $segment,
                'traversed' => array_slice($segments, 0, $i),
                'after'     => array_slice($segments, $i + 1),
            );
        }

        $root = $root[$segment];
    }

    return array(
        'context'   => $root,
        'name'      => '',
        'traversed' => $segments,
        'after'     => [],
    );
}

function matcher($routes, $context, $name)
{
    foreach ($routes as $route) {
        if ($route[2] === $name &&
            ($route[1] === null ||
            $context instanceof $route[1])
        ) {
            return $route[0];
        }
    }

    return null;
}