<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FliggyProductApiTest extends TestCase
{
    /**
     * 测试分页获取产品基本信息
     */
    public function test_get_product_list(): void
    {
        $response = $this->getJson('/fliggy/products/list', [
            'page_no' => 1,
            'page_size' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * 测试根据ID列表批量获取产品信息
     */
    public function test_get_products_by_ids(): void
    {
        $response = $this->postJson('/fliggy/products/batch', [
            'product_ids' => ['product_id_1', 'product_id_2'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * 测试获取产品详情
     */
    public function test_get_product_detail(): void
    {
        $productId = 'test_product_id';

        $response = $this->getJson("/fliggy/products/{$productId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * 测试获取产品价格库存
     */
    public function test_get_product_price_stock(): void
    {
        $productId = 'test_product_id';
        $beginTime = strtotime('2024-01-01') * 1000; // 13位时间戳
        $endTime = strtotime('2024-12-31') * 1000;   // 13位时间戳

        $response = $this->getJson("/fliggy/products/{$productId}/price-stock", [
            'begin_time' => $beginTime,
            'end_time' => $endTime,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * 测试批量同步所有产品
     */
    public function test_sync_all_products(): void
    {
        $response = $this->postJson('/fliggy/products/sync-all', [
            'page_size' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_products',
                    'total_pages',
                    'products'
                ],
                'message'
            ]);
    }

    /**
     * 测试参数验证 - 缺少必填参数
     */
    public function test_get_products_by_ids_missing_params(): void
    {
        $response = $this->postJson('/fliggy/products/batch', []);

        $response->assertStatus(422); // Laravel 验证失败返回 422
    }

    /**
     * 测试参数验证 - 超出页大小限制
     */
    public function test_get_product_list_exceeds_page_size(): void
    {
        $response = $this->getJson('/fliggy/products/list', [
            'page_no' => 1,
            'page_size' => 200, // 超出最大限制 100
        ]);

        $response->assertStatus(422); // 验证失败
    }
}
