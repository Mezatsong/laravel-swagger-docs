<?php namespace Mezatsong\SwaggerDocs\DataObjects;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Routing\Route as LaravelRoute;

/**
 * Class Route
 * @package Mezatsong\SwaggerDocs\DataObjects
 */
class Route {

    /**
     * Laravel route instance
     * @var LaravelRoute
     */
    protected LaravelRoute $route;

    /**
     * Laravel middleware information
     * @var array
     */
    protected array $middleware;

    /**
     * Get original URI for route
     * @return string
     */
    public function originalUri(): string {
        $uri = $this->route->uri();
        if (!Str::startsWith($uri, '/')) {
            $uri = '/' . $uri;
        }
        return $uri;
    }

    /**
     * Get URI
     * @return string
     */
    public function uri(): string {
        return \strip_optional_char($this->originalUri());
    }

    /**
     * Get middleware information
     * @return array|Middleware[]
     */
    public function middleware(): array {
        return $this->middleware;
    }

    /**
     * Get route name
     * @return string|null
     */
    public function name(): ?string {
        return $this->route->getName();
    }

    /**
     * Get route action name
     * @return string
     */
    public function action(): string {
        return $this->route->getActionName();
    }

    /**
     * Get route methods
     * @return array
     */
    public function methods(): array {
        return array_map('strtolower', $this->route->methods());
    }

    /**
     * Route constructor.
     * @param LaravelRoute $route
     */
    public function __construct(LaravelRoute $route) {
        $this->route = $route;
        $this->middleware = $this->formatMiddleware();
    }

    /**
     * Format middleware information
     * @return array
     */
    protected function formatMiddleware(): array {
        $middleware = $this->route->getAction()['middleware'] ?? [];
        return array_map(function (string $middleware): Middleware {
            return new Middleware($middleware);
        }, Arr::wrap($middleware));
    }

}
