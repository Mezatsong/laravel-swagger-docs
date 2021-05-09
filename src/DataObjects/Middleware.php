<?php namespace Mezatsong\SwaggerDocs\DataObjects;

use Illuminate\Support\Arr;

/**
 * Class Middleware
 * @package Mezatsong\SwaggerDocs\DataObjects
 */
class Middleware {

    /**
     * Middleware name
     * @var string
     */
    private string $name;

    /**
     * Middleware parameters
     * @var array
     */
    private array $parameters;

    /**
     * Middleware constructor.
     * @param string $middleware
     */
    public function __construct(string $middleware) {
        $tokens = explode(':', $middleware, 2);
        $this->name = Arr::first($tokens);
        $this->parameters = \count($tokens) > 1 ? explode(',', Arr::last($tokens)): [];
    }

    /**
     * Get middleware name
     * @return string
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * Get middleware parameters
     * @return array
     */
    public function parameters(): array {
        return $this->parameters;
    }

}
