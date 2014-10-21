<?php

namespace Guide42\Traba;

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