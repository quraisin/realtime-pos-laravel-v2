<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::where('username', 'cashier@admin')->first();
    }

    public function test_cashier_can_retrieve_analytics_summary_kpis_and_charts(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/analytics/summary?period=7days');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'kpis' => [
                    'total_revenue',
                    'total_orders',
                    'average_order_value',
                    'total_items_sold',
                    'avg_kitchen_prep_minutes',
                ],
                'payment_methods',
                'order_types',
                'top_products',
                'hourly_data',
                'daily_data',
            ]);
    }

    public function test_analytics_responds_to_different_periods(): void
    {
        foreach (['today', 'yesterday', '30days', 'this_month'] as $period) {
            $response = $this->actingAs($this->cashier)->getJson("/api/analytics/summary?period={$period}");
            $response->assertStatus(200)
                ->assertJson(['success' => true, 'period' => $period]);
        }
    }
}
