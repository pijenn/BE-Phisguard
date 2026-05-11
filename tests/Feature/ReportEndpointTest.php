<?php

namespace Tests\Feature;

use App\Services\MlPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReportEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_endpoint_stores_ml_prediction_result(): void
    {
        $this->app->instance(MlPredictionService::class, new class extends MlPredictionService {
            public function predict(string $message): array
            {
                return [
                    'label' => 'phishing',
                    'risk_score' => 92,
                    'priority' => 'high',
                    'reason' => 'Prediksi phishing dari model test',
                ];
            }
        });

        $payload = [
            'channel_chat' => 'WhatsApp',
            'sender_account' => '08123456789',
            'chat_text' => 'Klik link hadiah ini',
        ];

        $response = $this->postJson('/api/report', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Report submitted successfully')
            ->assertJsonPath('ml_result.label', 'phishing')
            ->assertJsonPath('ml_result.risk_score', 92)
            ->assertJsonPath('ml_result.priority', 'high');

        $this->assertDatabaseHas('reports', [
            'channel_chat' => 'WhatsApp',
            'sender_account' => '08123456789',
            'chat_text' => 'Klik link hadiah ini',
        ]);

        $this->assertDatabaseHas('ml_results', [
            'label' => 'phishing',
            'risk_score' => 92,
            'priority' => 'high',
            'reason' => 'Prediksi phishing dari model test',
        ]);
    }

    public function test_report_endpoint_uses_fallback_when_ml_service_fails(): void
    {
        $this->app->instance(MlPredictionService::class, new class extends MlPredictionService {
            public function predict(string $message): array
            {
                throw new RuntimeException('ML script failed');
            }
        });

        $payload = [
            'channel_chat' => 'Telegram',
            'sender_account' => '08111111111',
            'chat_text' => 'Pesan mencurigakan',
        ];

        $response = $this->postJson('/api/report', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Report submitted successfully')
            ->assertJsonPath('ml_result.label', 'non-phishing')
            ->assertJsonPath('ml_result.risk_score', 0)
            ->assertJsonPath('ml_result.priority', 'low')
            ->assertJsonPath('ml_result.reason', 'ML service unavailable');

        $this->assertDatabaseHas('ml_results', [
            'label' => 'non-phishing',
            'risk_score' => 0,
            'priority' => 'low',
            'reason' => 'ML service unavailable',
        ]);
    }
}
