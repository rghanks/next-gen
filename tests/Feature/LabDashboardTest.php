<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Lab\Models\LabOrder;
use App\Models\User;
use Tests\TestCase;

class LabDashboardTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        $response = $this->get(route('lab.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_system_overview_dashboard(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get(route('lab.dashboard'));

        $response->assertOk();
        $response->assertViewIs('lab.dashboard.index');
        $response->assertSeeText('System Overview & Command Center', false);
        $response->assertSeeText('Mode-wise Payment Collection Report', false);
        $response->assertSeeText('Complete Diagnostic Lifecycle Funnel', false);
        $response->assertSeeText('Recent Laboratory Orders & Live Activity Feed', false);
        $response->assertSeeText('High-Volume Laboratory Tests', false);
        $response->assertSeeText('7-Day Orders & Revenue Trend', false);
        $response->assertSeeText('Patient Registration & Visit Log Audit', false);
        $response->assertSeeText('System Users & Staff Directory Breakdown', false);
        $response->assertDontSeeText('Urgent Panic Alerts');
    }

    public function test_dashboard_supports_date_range_filtering(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Preset: yesterday
        $responseYesterday = $this->actingAs($user)->get(route('lab.dashboard', ['period' => 'yesterday']));
        $responseYesterday->assertOk();
        $responseYesterday->assertSee('Yesterday');

        // Preset: this_month
        $responseMonth = $this->actingAs($user)->get(route('lab.dashboard', ['period' => 'this_month']));
        $responseMonth->assertOk();
        $responseMonth->assertSee('This Month');

        // Custom range
        $responseCustom = $this->actingAs($user)->get(route('lab.dashboard', [
            'period' => 'custom',
            'date_from' => '2025-01-01',
            'date_to' => '2026-12-31',
        ]));
        $responseCustom->assertOk();
        $responseCustom->assertSee('Custom Range');
    }

    public function test_dashboard_live_api_stats_endpoint_returns_json(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('lab.api.dashboard.stats'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'timestamp',
            'today_orders',
            'today_revenue',
            'pending_collection',
            'in_process',
            'pending_verification',
            'unacknowledged_critical',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_topbar_navigation_contains_dashboard_link(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get(route('lab.billing.index'));

        $response->assertOk();
        $response->assertSee(route('lab.dashboard'));
        $response->assertSee('Dashboard');
    }
}
