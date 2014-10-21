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