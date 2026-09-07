<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\ViewException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorEligibleShiftsStaleRouteCacheReproductionTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    /**
     * Proves that under the normal fixture state, GET /operator/eligible-shifts returns HTTP 200.
     */
    public function test_eligible_shifts_renders_successfully_under_normal_conditions(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $response = $this->get(route('operator.eligible-shifts'));

        $response->assertOk();
        $response->assertSee(route('operator.shifts.create'), false);
        $response->assertSee('+ Create Field Operational Shift');
    }

    /**
     * Reproduces the exact production failure condition:
     * When route caching or OPcache retains the route collection from prior to the field operations
     * release (where 'operator.shifts.create' is missing), GET /operator/eligible-shifts fails with HTTP 500
     * due to RouteNotFoundException in eligible-shifts.blade.php.
     */
    public function test_reproduces_production_http_500_when_route_cache_lacks_operator_shifts_create(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        // Simulate the stale route collection from the previous deployment (0c016f3135e68d70f0e2e9561a7c8d27978da03d)
        // by removing 'operator.shifts.create' from the active RouteCollection.
        $originalRoutes = Route::getRoutes();
        $staleRoutes = new RouteCollection;
        foreach ($originalRoutes->getRoutes() as $route) {
            if ($route->getName() !== 'operator.shifts.create') {
                $staleRoutes->add($route);
            }
        }
        Route::setRoutes($staleRoutes);

        try {
            $this->assertFalse(Route::has('operator.shifts.create'));

            $response = $this->get('/operator/eligible-shifts');

            // Production manifests as HTTP 500
            $response->assertStatus(500);
        } finally {
            // Restore original routes
            Route::setRoutes($originalRoutes);
        }
    }

    public function test_reproduces_exact_routenotfoundexception_class_and_message(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $originalRoutes = Route::getRoutes();
        $staleRoutes = new RouteCollection;
        foreach ($originalRoutes->getRoutes() as $route) {
            if ($route->getName() !== 'operator.shifts.create') {
                $staleRoutes->add($route);
            }
        }
        Route::setRoutes($staleRoutes);

        $this->withoutExceptionHandling();

        try {
            $this->expectException(ViewException::class);
            $this->expectExceptionMessage('Route [operator.shifts.create] not defined. (View: '.resource_path('views/operator/eligible-shifts.blade.php').')');

            $this->get('/operator/eligible-shifts');
        } finally {
            Route::setRoutes($originalRoutes);
        }
    }
}
