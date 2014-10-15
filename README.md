Traba ~ Traversal Router
========================

An alternative to common pattern matching to route an URL to a resource.

The most common way of routing is by comparing the given URL to a set of
registered "patterns" (a.k.a. regular expressions). Each of them has some
metadata information such as the "controller" to call when the route match
with the request URL. If none of the "patterns" matched, an exception will be
raised that then is converted to a "404 Not Found" response.

Badges
------

[![Latest Stable Version](https://poser.pugx.org/guide42/traba/v/stable.svg)](https://packagist.org/packages/guide42/traba)
[![Build Status](https://travis-ci.org/guide42/traba.svg?branch=master)](https://travis-ci.org/guide42/traba)