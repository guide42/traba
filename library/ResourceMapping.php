<?php

namespace Guide42\Traba;

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