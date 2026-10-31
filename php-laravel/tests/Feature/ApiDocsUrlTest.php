<?php

namespace Tests\Feature;

use App\Http\Controllers\PatientController;
use App\Providers\AppServiceProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Scribe URL normalization: the separate PATCH route stays on the same documented path as PUT.
 */
class ApiDocsUrlTest extends TestCase
{
    public function test_patch_patient_is_documented_on_the_resource_path(): void
    {
        $routes = app('router')->getRoutes();
        $default = fn () => 'default';

        $patch = $routes->getByName('patients.partial-update');
        $put = $routes->getByName('patients.update');
        $this->assertNotNull($patch);
        $this->assertNotNull($put);

        $method = new ReflectionMethod(PatientController::class, 'update');

        $this->assertSame('api/patients/{id}', AppServiceProvider::normalizeDocsUrl($patch->uri(), $patch, $method, null, $default));
        $this->assertSame('default', AppServiceProvider::normalizeDocsUrl($put->uri(), $put, $method, null, $default));
        // Without Scribe's default callback, its resource normalization is used.
        $this->assertSame('api/patients/{id}', AppServiceProvider::normalizeDocsUrl($put->uri(), $put, $method));
    }
}
