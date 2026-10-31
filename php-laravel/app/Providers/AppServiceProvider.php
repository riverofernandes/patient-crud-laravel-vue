<?php

namespace App\Providers;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Extracting\Shared\UrlParamsNormalizer;
use Knuckles\Scribe\Scribe;
use ReflectionClass;
use ReflectionFunctionAbstract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API docs: PATCH /patients/{patient} is its own route (not the resource's PUT|PATCH, which Scribe documents
        // as PUT only), so Scribe does not recognize it as a resource route and would call its parameter
        // {patient_id}. Keep it on the same path as GET/PUT/DELETE (/api/patients/{id}).
        Scribe::normalizeEndpointUrlUsing(self::normalizeDocsUrl(...));
    }

    /**
     * @param  \ReflectionClass<object>|null  $controller
     * @param  (Closure(): string)|null  $default  Scribe's own normalization (passed as an extra argument)
     */
    public static function normalizeDocsUrl(
        string $url,
        Route $route,
        ReflectionFunctionAbstract $method,
        ReflectionClass $controller = null,
        Closure $default = null
    ): string {
        if ($route->getName() === 'patients.partial-update') {
            return 'api/patients/{id}';
        }

        return $default !== null ? $default() : UrlParamsNormalizer::normalizeParameterNamesInRouteUri($route, $method);
    }
}
