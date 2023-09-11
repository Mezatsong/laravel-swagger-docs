<?php namespace Mezatsong\SwaggerDocs;

use Exception;
use Mezatsong\SwaggerDocs\Exceptions\InvalidDefinitionException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Laravel\Passport\Passport;
use Mezatsong\SwaggerDocs\Parameters;
use Mezatsong\SwaggerDocs\DataObjects;
use phpDocumentor\Reflection\DocBlock\Tag;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\FormRequest;
use phpDocumentor\Reflection\DocBlockFactory;
use Laravel\Passport\Http\Middleware\CheckScopes;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;
use Mezatsong\SwaggerDocs\Definitions\DefinitionGenerator;
use Mezatsong\SwaggerDocs\Exceptions\InvalidAuthenticationFlow;
use Mezatsong\SwaggerDocs\Exceptions\SchemaBuilderNotFound;

/**
 * Class Generator
 * @package Mezatsong\SwaggerDocs
 */
class Generator {

    const OAUTH_TOKEN_PATH = '/oauth/token';

    const OAUTH_AUTHORIZE_PATH = '/oauth/authorize';

    /**
     * Configuration repository instance
     * @var Repository
     */
    protected Repository $configuration;

    /**
     * Route filter value
     * @var string|null
     */
    protected ?string $routeFilter;

    /**
     * Parser instance
     * @var DocBlockFactory
     */
    protected DocBlockFactory $parser;

    /**
     * DefinitionGenerator instance
     * @var DefinitionGenerator
     */
    protected DefinitionGenerator $definitionGenerator;

    /**
     * Indicates whether we have security definitions
     * @var bool
     */
    protected bool $hasSecurityDefinitions;

    /**
     * List of ignored routes and methods
     * @var array
     */
    protected array $ignored;

    /**
     * Items to be appended to documentation
     * @var array
     */
    protected array $append;

    /**
     * Generator constructor.
     * @param Repository $config
     * @param string|null $routeFilter
     */
    public function __construct(Repository $config, ?string $routeFilter = null) {
        $this->configuration = $config;
        $this->routeFilter = $routeFilter ?: $this->fromConfig('api_base_path');
        $this->parser = DocBlockFactory::createInstance();
        $this->hasSecurityDefinitions = false;
        $this->ignored = $this->fromConfig('ignored', []);
        $this->append = $this->fromConfig('append', []);
    }

    /**
     * Generate documentation
     * @return array
     * @throws InvalidAuthenticationFlow
     */
    public function generate(): array {
        $documentation = $this->generateBaseInformation();
        $applicationRoutes = $this->getApplicationRoutes();

        $this->definitionGenerator = new DefinitionGenerator(Arr::get($this->ignored, 'models'));
        Arr::set($documentation, 'components.schemas', $this->definitionGenerator->generateSchemas());

        if ($this->fromConfig('parse.security', false) /*&& $this->hasOAuthRoutes($applicationRoutes)*/) {
            Arr::set($documentation, 'components.securitySchemes', $this->generateSecurityDefinitions());
            $this->hasSecurityDefinitions = true;
        }

        $basePath = $this->routeFilter ?: $this->fromConfig('api_base_path');
        foreach ($applicationRoutes as $route) {
            if ($this->isFilteredRoute($route)) {
                continue;
            }

            $uri = Str::replaceFirst($basePath, '', $route->uri());
            $pathKey = 'paths.' . $uri;

            if (!Arr::has($documentation, $pathKey)) {
                Arr::set($documentation, $pathKey, []);
            }

            $tagFromPrefix = explode('/', $uri)[0] ?: explode('/', $uri)[1] ?? null;

            foreach ($route->methods() as $method) {
                if (in_array($method, Arr::get($this->ignored, 'methods'))) {
                    continue;
                }
                $methodKey = $pathKey . '.' . $method;
                Arr::set($documentation, $methodKey, $this->generatePath($route, $method, $tagFromPrefix));
            }
        }

        return $documentation;
    }

    /**
     * Generate base information
     * @return array
     */
    private function generateBaseInformation(): array {
        return [
            'openapi'               =>  '3.0.0',
            'info'                  =>  [
                'title'             =>  $this->fromConfig('title'),
                'description'       =>  $this->fromConfig('description'),
                'version'           =>  $this->fromConfig('version')
            ],
            'servers'               =>  $this->generateServersList(),
            'paths'                 =>  [],
            'tags'                  =>  $this->fromConfig('tags'),
        ];
    }

    /**
     * Get list of application routes
     * @return array|DataObjects\Route[]
     */
    private function getApplicationRoutes(): array {
        return array_map(function (Route $route): DataObjects\Route {
            return new DataObjects\Route($route);
        }, app('router')->getRoutes()->getRoutes());
    }

    /**
     * Get value from configuration
     * @param string $key
     * @param mixed $default
     * @return array|mixed
     */
    private function fromConfig(string $key, $default = null) {
        return $this->configuration->get('swagger.' . $key, $default);
    }

    /**
     * Generate servers list from configuration
     * @return array
     */
    private function generateServersList(): array {
        $rawServers = $this->fromConfig('servers');
        $servers = [];

        foreach ($rawServers as $index => $server) {
            if (is_array($server)) {
                $url = Arr::get($server, 'url', null);
                $description = Arr::get($server, 'description', null);
                if ($url) {
                    array_push($servers, [
                        'url'           =>  $url,
                        'description'   =>  $description ?: sprintf('%s Server #%d', $this->fromConfig('title'), $index + 1)
                    ]);
                }
            } else {
                array_push($servers, [
                    'url'           =>  $server,
                    'description'   =>  sprintf('%s Server #%d', $this->fromConfig('title'), $index + 1)
                ]);
            }
        }

        if (\count($servers) === 0) {
            array_push($servers, [
                'url'           =>  env('APP_URL'),
                'description'   =>  env('APP_NAME') . ' Main Server'
            ]);
        }

        return $servers;
    }

    /**
     * @param array|DataObjects\Route[] $applicationRoutes
     * @return bool
     */
    private function hasOAuthRoutes(array $applicationRoutes): bool {
        foreach ($applicationRoutes as $route) {
            $uri = $route->uri();
            if (
                $uri === self::OAUTH_TOKEN_PATH ||
                $uri === self::OAUTH_AUTHORIZE_PATH
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether this is filtered route
     * @param DataObjects\Route $route
     * @return bool
     */
    private function isFilteredRoute(DataObjects\Route $route): bool  {
        $ignoredRoutes = Arr::get($this->ignored, 'routes');
        $routeName = $route->name();
        $routeUri = $route->uri();
        if ($routeName) {
            if (in_array($routeName, $ignoredRoutes)) {
                return true;
            }
        }

        if (in_array($routeUri, $ignoredRoutes)) {
            return true;
        }
        if ($this->routeFilter) {
            return !preg_match('/^' . preg_quote($this->routeFilter, '/') . '/', $route->uri());
        }
        return false;
    }


    /**
     * Generate Path information
     * @param DataObjects\Route $route
     * @param string $method
     * @return array
     */
    private function generatePath(DataObjects\Route $route, string $method, ?string $tagFromPrefix): array {
        $actionMethodInstance = $this->getActionMethodInstance($route);
        $documentationBlock = $actionMethodInstance ? ($actionMethodInstance->getDocComment() ?: ''): '';

        $documentation = $this->parseActionDocumentationBlock($documentationBlock, $route->uri());

        $this->addActionsParameters($documentation, $route, $method, $actionMethodInstance);

        $this->addActionsResponses($documentation);

        if ($this->hasSecurityDefinitions) {
            $this->addActionScopes($documentation, $route);
        }

        if (!Arr::has($documentation, 'tags') || \count(Arr::get($documentation, 'tags')) == 0) {

            switch($this->fromConfig('default_tags_generation_strategy')) {
                case 'controller':
                    $this->addTagsFromControllerName($documentation, $actionMethodInstance);
                    break;
                case 'prefix':
                    $tagFromPrefix && Arr::set($documentation, 'tags', [$tagFromPrefix]);
                    break;
                default: // Do nothing, all operation will be in one default tag
            }
        }

        return $documentation;
    }



    private function addTagsFromControllerName(array & $documentation, ?ReflectionMethod $actionMethodInstance): void {
        if ($actionMethodInstance == null) return;
        $classInstance = $actionMethodInstance->getDeclaringClass();
        $className = $classInstance ? $classInstance->getShortName() : null;
        if ($className) {
            $tagName = ucwords(implode(' ',preg_split('/(?=[A-Z])/', $className))); // convert camel case to words
            $tagName = str_replace('Controller', '', $tagName);
            Arr::set($documentation, 'tags', [ $tagName ]);
        }
    }

    /**
     * Get action method instance
     * @param DataObjects\Route $route
     * @return ReflectionMethod|null
     */
    private function getActionMethodInstance(DataObjects\Route $route): ?ReflectionMethod {
        [$class, $method] = Str::parseCallback($route->action());
        if (!$class || !$method) {
            return null;
        }
        try {
            return new ReflectionMethod($class, $method);
        } catch (ReflectionException $exception) {
            return null;
        }
    }

    /**
     * Parse action documentation block
     * @param string $documentationBlock
     * @return array
     */
    private function parseActionDocumentationBlock(string $documentationBlock, string $uri): array {
        $documentation = [
            'summary'       =>  '',
            'description'   =>  '',
            'deprecated'    =>  false,
            'responses'     =>  [],
        ];

        if (empty($documentationBlock) || !$this->fromConfig('parse.docBlock', false)) {
            return $documentation;
        }

        try {
            $parsedComment = $this->parser->create($documentationBlock);
            Arr::set($documentation, 'deprecated', $parsedComment->hasTag('deprecated'));
            Arr::set($documentation, 'summary', $parsedComment->getSummary());
            Arr::set($documentation, 'description', (string) $parsedComment->getDescription());

            $hasRequest = $parsedComment->hasTag('Request');
            $hasResponse = $parsedComment->hasTag('Response');

            if ($hasRequest) {
                $firstTag = Arr::first($parsedComment->getTagsByName('Request'));
                $tagData = $this->parseRawDocumentationTag($firstTag);
                foreach ($tagData as $row) {
                    [$key, $value] = array_map(fn(string $value) => trim($value), explode(':', $row));
                    if ($key === 'tags') {
                        $value = array_map(fn(string $string) => trim($string), explode(',', $value));
                    }
                    Arr::set($documentation, $key, $value);
                }
            }

            if ($hasResponse) {
                $responseTags = $parsedComment->getTagsByName('Response');
                foreach ($responseTags as $rawTag) {
                    $tagData = $this->parseRawDocumentationTag($rawTag);
                    $responseCode = '';
                    foreach ($tagData as $value) {
                        [$key, $value] = array_map(fn(string $value) => trim($value), explode(':', $value));
                        if ($key === 'code') {
                            $responseCode = $value;
                            $documentation['responses'][$value] = [
                                'description'   =>  '',
                            ];
                        } else if ($key === 'description') {
                            $documentation['responses'][$responseCode]['description'] = $value;
                        } else if ($key === 'ref') {

                            $value = str_replace(' ', '', $value);
                            $matches = [];

                            if (Str::startsWith($value, '[') && Str::endsWith($value, ']')) {
                                $modelName = trim(Str::replaceFirst('[', '' , Str::replaceLast(']', '' , $value)));
                                $ref = $this->toSwaggerModelPath($modelName);
                                $items = [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        '$ref' => $ref
                                    ]
                                ];
                                $documentation['responses'][$responseCode]['content']['application/json']['schema'] = $items;
                            } else if (preg_match("(([A-Za-z]{1,})\(([A-Za-z]{1,})\))", $value, $matches) === 1) {
                                $schema = $this->generateCustomResponseSchema(
                                    $matches[1],
                                    $matches[2],
                                    $uri
                                );
                                if (\count($schema) > 0) {
                                    $documentation['responses'][$responseCode]['content']['application/json']['schema'] = $schema;
                                }
                            } else {
                                $ref = $this->toSwaggerModelPath($value);
                                $documentation['responses'][$responseCode]['content']['application/json']['schema']['$ref'] = $ref;
                            }

                        }
                    }
                }
            }

        } catch (Exception $exception) {
            if ($exception instanceof SchemaBuilderNotFound) {
                throw $exception;
            }
        }

        return $documentation;
    }


    /**
     * Read schemas builder config and call the matched one
     *
     * @throws SchemaBuilderNotFound
     */
    private function generateCustomResponseSchema(string $operation, string $model, string $uri) {
        $ref = $this->toSwaggerModelPath($model);
        $schemaBuilders = $this->fromConfig('schema_builders');

        if (!Arr::has($schemaBuilders, $operation)) {
            throw new SchemaBuilderNotFound("schema builder $operation not found in swagger.schema_builders config when generating $uri");
        }

        $actionClass = new ReflectionClass($schemaBuilders[$operation]);

        try {
            return $actionClass->newInstanceWithoutConstructor()->build($ref, $uri);
        } catch(Exception $e) {
            throw new Exception("SchemaBuilder must implements Mezatsong\SwaggerDocs\Responses\SchemaBuilder interface");
        }
    }


    /**
     * Turn a model name to swagger path to that model or
     * return the same string if it's already a valide path
     * @param string $value
     * @return string
     */
    private function toSwaggerModelPath(string $value): string {
        if (!Str::startsWith($value, '#/components/schemas/')) {
            foreach ($this->definitionGenerator->getModels() as $item) {
                if (Str::endsWith($item, $value)) {
                    return "#/components/schemas/$value";
                }
            }
        }

        return $value;
    }


    /**
     * Append action responses
     * @param array $information
     * @param DataObjects\Route $route
     * @param string $method
     * @param ReflectionMethod|null $actionInstance
     */
    private function addActionsResponses(array & $information): void {

        if (\count(Arr::get($information, 'responses')) === 0) {
            Arr::set($information, 'responses', [
                '200' => [
                    'description' => 'OK',
                ]
            ]);
        }

        foreach ($this->append['responses'] as $code => $response) {
            if (!Arr::has($information, 'responses.' . $code)) {
                Arr::set($information, 'responses.' . $code, $response);
            }
        }
    }

    /**
     * Append action parameters
     * @param array $information
     * @param DataObjects\Route $route
     * @param string $method
     * @param ReflectionMethod|null $actionInstance
     */
    private function addActionsParameters(array & $information, DataObjects\Route $route, string $method, ?ReflectionMethod $actionInstance): void {
        $rules = $this->retrieveFormRules($actionInstance) ?: [];
        $parameters = (new Parameters\PathParametersGenerator($route->originalUri()))->getParameters();
        $requestBody = [];

        if (\count($rules) > 0) {
            $parametersGenerator = $this->getParametersGenerator($rules, $method);
            if ('body' == $parametersGenerator->getParameterLocation()) {
                $requestBody = $parametersGenerator->getParameters();
            } else {
                $parameters = array_merge($parameters, $parametersGenerator->getParameters());
            }
        }

        if (\count($parameters) > 0) {
            Arr::set($information, 'parameters', $parameters);
        }

        if (\count($requestBody) > 0) {
            Arr::set($information, 'requestBody', $requestBody);
        }
    }

    /**
     * Add action scopes
     * @param array $information
     * @param DataObjects\Route $route
     */
    private function addActionScopes(array & $information, DataObjects\Route $route) {
        foreach ($route->middleware() as $middleware) {
            if ($this->isSecurityMiddleware($middleware)) {
                $security = [
                    '_temp'     =>  $middleware->parameters(),
                ];
                foreach ($this->fromConfig('authentication_flow') as $definition => $value) {
                    $parameters = ($definition === 'OAuth2') ? $middleware->parameters() : [];
                    $security[$definition] = $parameters;
                }
                if (\count(Arr::flatten($security)) > 0) {
                    unset($security['_temp']);
                    Arr::set($information, 'security', [$security]);
                }
            }
        }
    }

    /**
     * Retrieve form rules
     * @param ReflectionMethod|null $actionInstance
     * @return array
     */
    private function retrieveFormRules(?ReflectionMethod $actionInstance): array {
        if (!$actionInstance) {
            return [];
        }
        $parameters = $actionInstance->getParameters();

        foreach ($parameters as $parameter) {
            $class = $parameter->getType();
            if (!$class) {
                continue;
            }
            $className = $class->getName();
            if (is_subclass_of($className, FormRequest::class)) {
                return (new $className)->rules();
            }
        }
        return [];
    }

    /**
     * Get appropriate parameters generator
     * @param array $rules
     * @param string $method
     * @return Parameters\Interfaces\ParametersGenerator
     */
    private function getParametersGenerator(array $rules, string $method): Parameters\Interfaces\ParametersGenerator {
        switch ($method) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\BodyParametersGenerator($rules);
            default:
                return new Parameters\QueryParametersGenerator($rules);
        }
    }

    /**
     * Check whether specified middleware belongs to registered security middlewares
     * @param DataObjects\Middleware $middleware
     * @return bool
     */
    private function isSecurityMiddleware(DataObjects\Middleware $middleware) {
        return in_array("$middleware", $this->fromConfig('security_middlewares'));
    }

    /**
     * Check whether specified middleware belongs to Laravel Passport
     * @param DataObjects\Middleware $middleware
     * @return bool
     */
    private function isPassportScopeMiddleware(DataObjects\Middleware $middleware) {
        $resolver = $this->getMiddlewareResolver($middleware->name());
        return $resolver === CheckScopes::class || CheckForAnyScope::class;
    }

    /**
     * Get middleware resolver class
     * @param string $middleware
     * @return string|null
     */
    private function getMiddlewareResolver(string $middleware): ?string {
        $middlewareMap = app('router')->getMiddleware();
        return $middlewareMap[$middleware] ?? null;
    }

    /**
     * Parse raw documentation tag
     * @param Generic|Tag $rawTag
     * @return array
     */
    private function parseRawDocumentationTag($rawTag): array {
        return Str::of((string) $rawTag)
            ->replace('({', '')
            ->replace('})', '')
            ->replace(["\r\n", "\n\r", "\r", PHP_EOL], "\n")
            ->explode("\n")
            ->filter(fn(string $value) => strlen($value) > 1)
            ->map(fn(string $value) => rtrim(trim($value), ','))
            ->toArray();
    }

    /**
     * Generate security definitions
     * @return array[]
     * @throws InvalidAuthenticationFlow|InvalidDefinitionException
     */
    private function generateSecurityDefinitions(): array {
        $authenticationFlows = $this->fromConfig('authentication_flow');

        $definitions = [];

        foreach ($authenticationFlows as $definition => $flow) {
            $this->validateAuthenticationFlow($definition, $flow);
            $definitions[$definition] = $this->createSecurityDefinition($definition, $flow);
        }

        return $definitions;
    }

    /**
     * Create security definition
     * @param string $definition
     * @param string $flow
     * @return array|string[]
     */
    private function createSecurityDefinition(string $definition, string $flow): array {
        switch ($definition) {
            case 'OAuth2':
                $definitionBody = [
                    'type'      =>  'oauth2',
                    'flows'      =>  [
                        $flow => []
                    ],
                ];
                $flowKey = 'flows.' . $flow . '.';
                if (in_array($flow, ['implicit', 'authorizationCode'])) {
                    Arr::set($definitionBody, $flowKey . 'authorizationUrl', $this->getEndpoint(self::OAUTH_AUTHORIZE_PATH));
                }

                if (in_array($flow, ['password', 'application', 'authorizationCode'])) {
                    Arr::set($definitionBody, $flowKey . 'tokenUrl', $this->getEndpoint(self::OAUTH_TOKEN_PATH));
                }
                Arr::set($definitionBody, $flowKey . 'scopes', $this->generateOAuthScopes());
                return $definitionBody;
            case 'bearerAuth':
                return [
                    'type'          =>  $flow,
                    'scheme'        =>  'bearer',
                    'bearerFormat'  =>  'JWT'
                ];
        }
        return [];
    }

    /**
     * Validate selected authentication flow
     * @param string $definition
     * @param string $flow
     * @throws InvalidAuthenticationFlow|InvalidDefinitionException
     */
    private function validateAuthenticationFlow(string $definition, string $flow): void {
        $definitions = [
            'OAuth2'            =>  ['password', 'application', 'implicit', 'authorizationCode'],
            'bearerAuth'        =>  ['http']
        ];

        if (!Arr::has($definitions, $definition)) {
            throw new InvalidDefinitionException('Invalid Definition, please select from the following: ' . implode(', ', array_keys($definitions)));
        }

        $allowed = $definitions[$definition];
        if (!in_array($flow, $allowed)) {
            throw new InvalidAuthenticationFlow('Invalid Authentication Flow, please select one from the following: ' . implode(', ', $allowed));
        }
    }

    /**
     * Get endpoint
     * @param string $path
     * @return string
     */
    private function getEndpoint(string $path): string {
        $host = $this->fromConfig('host');
        if (!Str::startsWith($host,'http://') || !Str::startsWith($host,'https://')) {
            $schema = swagger_is_connection_secure() ? 'https://' : 'http://';
            $host = $schema . $host;
        }
        return rtrim($host, '/') . $path;
    }

    /**
     * Generate OAuth scopes
     * @return array
     */
    private function generateOAuthScopes(): array {
        if (!class_exists(Passport::class)) {
            return [];
        }

        $scopes = Passport::scopes()->toArray();
        return array_combine(array_column($scopes, 'id'), array_column($scopes, 'description'));
    }

}
