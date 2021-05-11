# Laravel Swagger Docs

Simple to use OAS3 compatible documentation generator.  
Also includes Swagger UI.   

## About
This package is heavily inspired by the [darki73/laravel-swagger](https://github.com/darki73/laravel-swagger) and [kevupton/laravel-swagger](https://github.com/kevupton/laravel-swagger).  
Usage is pretty similar to the [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger) with the difference being:
1. OAS3 support
2. Custom decorators
3. Custom responses
4. Automatic generation (assuming relevant configuration option is turned on)
5. Inclusion of Swagger UI
6. Models generations
7. Generate operation tags based on route prefix or controller's name


## Installation
#### Install package through composer
```shell
composer require mezatsong/laravel-swagger-docs
```
#### Publish configuration files and views
```shell
php artisan vendor:publish --provider "Mezatsong\SwaggerDocs\SwaggerServiceProvider"
```
#### Edit the `swagger.php` configuration file for your liking

## Usage

Laravel Swagger Docs works based on recommended practices by Laravel. It will parse your routes and generate a path object for each one. If you inject Form Request classes in your controller's actions as request validation, it will also generate the parameters for each request that has them. For the parameters, it will take into account wether the request is a GET/HEAD/DELETE or a POST/PUT/PATCH request and make its best guess as to the type of parameter object it should generate. It will also generate the path parameters if your route contains them. Finally, this package will also scan any documentation you have in your action methods and add it as summary and description to that path, along with any appropriate annotations such as @deprecated.

One thing to note is this library leans on being explicit. It will choose to include keys even if they have a default. For example it chooses to say a route has a deprecated value of false rather than leaving it out. I believe this makes reading the documentation easier by not leaving important information out. The file can be easily cleaned up afterwards if the user chooses to leave out the defaults.

### Command line
Generating the swagger documentation is easy, simply run `php artisan laravel-swagger:generate` in your project root. The output of the command will be stored in your storage path linked in config file.

If you wish to generate docs for a subset of your routes, you can pass a filter using `--filter`, for example: `php artisan laravel-swagger:generate --filter="/api"`

You can also configure your swagger.php file to always generate schema when accessing Swagger UI or just by adding this line in your .env: `SWAGGER_GENERATE_ALWAYS=true`

By default, laravel-swagger prints out the documentation in json format, if you want it in YAML format you can override the format using the `--format` flag. Make sure to have the yaml extension installed if you choose to do so.

Format options are:
`json`
`yaml`


### @Request() decorator
You can have only one `@Request()` decorator.
```php
/**
* You can also do this, first line will be "summary"
*
* And anything 1 * apart from the "summary" will count as "description"
*
* @Request({
*     summary: Title of the route,
*     description: This is a longer description for the route which will be visible once the panel is expanded,
*     tags: Authentication,Users
* })
*/
public function someMethod(Request $request) {}
```

### @Response() decorator
You can have multiple `@Response` decorators
- The `code` property is required and must be the first in propery
- You can use the optional `description` property to desscribe your response
- You can use the optional `ref` property to refer a model, you can also wrap that model in [] to refer an array of that model or use the full model path inside, finally you can use a schema builder
```php
/**
* @Response({
*     code: 200
*     description: return user model
*     ref: User
* })
* @Response({
*     code: 400
*     description: Bad Request, array of APIError model
*     ref: [APIError]
* })
* @Response({
*     code: 302
*     description: Redirect
* })
* @Response({
*     code: 500
*     description: Internal Server Error
* })
*/
public function someMethod(Request $request) {}

/**
 * You can also refer object directly
 * 
 * 
 * @Response({
 *     code: 200
 *     description: direct user model reference
 *     ref: #/components/schemas/User
 * })
 */
public function someMethod2(Request $request) {}

/**
 * Using P schema builder for Laravel Pagination
 * 
 * @Response({
 *     code: 200
 *     description: a laravel pagination instance with User model
 *     ref: P(User)
 * })
 */
public function someMethod3(Request $request) {}
```

##### Note: You can see all available schema builder or create your own schema builder, explore swagger.schema_builders config for more informations.

### Custom Validators
These validators are made purely for visual purposes, however, some of them can actually do validation

#### swagger_default
```php
$rules = [
    'locale'        =>  'swagger_default:en_GB'
];
```
#### swagger_min
```php
$rules = [
    'page'          =>  'swagger_default:1|swagger_min:1', // This will simply display the 'minimum' value in the documentation
    'page'          =>  'swagger_default:1|swagger_min:1:fail' // This will also fail if the `page` parameter will be less than 1
];
```

#### swagger_max
```php
$rules = [
    'take'          =>  'swagger_default:1|swagger_min:1|swagger_max:50', // This will simply display the 'maximum' value in the documentation
    'take'          =>  'swagger_default:1|swagger_min:1|swagger_max:50:fail' // This will also fail if the `take` parameter will be greater than 50
];
```
