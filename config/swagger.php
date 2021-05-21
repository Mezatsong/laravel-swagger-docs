<?php

return [

    /**
     * Weather swagger is enabled
     * if you set false, this will
     * only hide swagger's route
     */
    'enable'                    => env('SWAGGER_ENABLE', false),

    /**
     * API Title
     */
    'title'                     =>  env('APP_NAME', 'Application API Documentation'),

    /**
     * API Description
     */
    'description'               =>  env('APP_DESCRIPTION', 'Documentation for the Application API'),

    /**
     * API Version
     */
    'version'                   =>  env('APP_VERSION', '1.0.0'),

    /**
     * API Host
     */
    'host'                      =>  env('APP_URL'),

    /**
     * API Base path
     */
    'api_base_path'             => env('SWAGGER_API_BASE_PATH', '/api'),

    /**
     * API Path
     */
    'path'                      =>  env('SWAGGER_PATH', '/documentation'),

    /**
     * API Storage Path
     */
    'storage'                   =>  env('SWAGGER_STORAGE', storage_path('swagger')),

    /**
     * API Views Path
     */
    'views'                     =>  base_path('resources/views/vendor/swagger'),

    /**
     * API Translations Path
     */
    'translations'              =>  base_path('resources/lang/vendor/swagger'),

    /**
     * Servers list
     * ['https://server.name.org'] OR [ [ "url" => "", "description" => "" ] ]
     */
    'servers'                   =>  env('APP_URL', false) ? [env('APP_URL') . env('SWAGGER_API_BASE_PATH', '/api')] : [],

    /**
     * Always generate schema when accessing Swagger UI
     */
    'generated'                 =>  env('SWAGGER_GENERATE_ALWAYS', true),


    /**
     * Append additional data to ALL routes
     */
    'append'                    =>  [
        'responses'             =>  [
            '401'               =>  [
                'description'   =>  '(Unauthorized) Invalid or missing Access Token'
            ]
        ]
    ],

    /**
     * List of ignored items (routes and methods)
     * They will be hidden from the documentation
     */
    'ignored' => [
        'methods' => [
            'head',
            'options'
        ],
        'routes' => [
            'passport.authorizations.authorize',
            'passport.authorizations.approve',
            'passport.authorizations.deny',
            'passport.token',
            'passport.tokens.index',
            'passport.tokens.destroy',
            'passport.token.refresh',
            'passport.clients.index',
            'passport.clients.store',
            'passport.clients.update',
            'passport.clients.destroy',
            'passport.scopes.index',
            'passport.personal.tokens.index',
            'passport.personal.tokens.store',
            'passport.personal.tokens.destroy',


            '/_ignition/health-check',
            '/_ignition/execute-solution',
            '/_ignition/share-report',
            '/_ignition/scripts/{script}',
            '/_ignition/styles/{style}',
            env('SWAGGER_PATH', '/documentation'),
            env('SWAGGER_PATH', '/documentation') . '/content'
        ],

        'models' => []
    ],

    /**
     * Tags
     */
    'tags'                      =>  [
//        [
//            'name'          =>  'Authentication',
//            'description'   =>  'Routes related to Authentication'
//        ],
    ],

    /**
     * Specifie the default tag generation strategy.
     * It can be 'prefix' (using the first non null part of uri splitted with /)
     * or 'controller' (using the controller name translated from camel case to words)
     * or other else to leave all operation in a one default tag
     */
    'default_tags_generation_strategy' =>  env('SWAGGER_DEFAULT_TAGS_GENERATION_STRATEGY', 'prefix'),

    /**
     * Parsing strategy
     */
    'parse'                     =>  [
        'docBlock'              =>  true,
        'security'              =>  true,
    ],

    /**
     * Authentication flow values
     */
    'authentication_flow'       =>  [
        //'OAuth2'                =>  'authorizationCode',
        'bearerAuth'            =>  'http',
    ],

    /**
     * List here your security middlewares
     * The paths under these middlewares will be protected
     */
    'security_middlewares'      =>  [
        'auth:api',
        'auth:sanctum',
    ],

    /**
     * Schema builder for custom swagger responses.
     *
     * You can implement your own schema builder, see example in this existing implementation
     * Note that Schema builder must implement Mezatsong\SwaggerDocs\Responses\SchemaBuilder
     */
    'schema_builders'            => [
        'P' => \Mezatsong\SwaggerDocs\Responses\SchemaBuilders\LaravelPaginateSchemaBuilder::class,
        'SP' => \Mezatsong\SwaggerDocs\Responses\SchemaBuilders\LaravelSimplePaginateSchemaBuilder::class,
    ]

];
