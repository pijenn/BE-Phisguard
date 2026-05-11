<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class MlPredictionService
{
    public function predict(string $message): array
    {
        $pythonBin = $this->resolvePythonBin();
        $scriptPath = base_path(config('services.ml.predict_script', 'ml/predict_model.py'));
        $timeout = (int) config('services.ml.timeout', 20);

        $result = Process::timeout($timeout)->run([
            $pythonBin,
            $scriptPath,
            '--message',
            $message,
            '--output',
            'json',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ML process failed: '.$result->errorOutput());
        }

        $decoded = json_decode(trim($result->output()), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid ML response: '.$result->output());
        }

        $label = $decoded['label'] ?? null;
        $riskScore = $decoded['risk_score'] ?? null;
        $priority = $decoded['priority'] ?? null;
        $reason = $decoded['reason'] ?? null;

        if (! in_array($label, ['phishing', 'non-phishing'], true)) {
            throw new RuntimeException('Unsupported ML label: '.(string) $label);
        }

        if (! is_numeric($riskScore)) {
            throw new RuntimeException('Invalid ML risk score.');
        }

        if (! in_array($priority, ['low', 'medium', 'high'], true)) {
            throw new RuntimeException('Unsupported ML priority: '.(string) $priority);
        }

        return [
            'label' => $label,
            'risk_score' => (int) round((float) $riskScore),
            'priority' => $priority,
            'reason' => is_string($reason) && $reason !== '' ? $reason : 'Tidak ada alasan tersedia',
            'raw' => $decoded,
        ];
    }

    private function resolvePythonBin(): string
    {
        $configured = config('services.ml.python_bin');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $venvPython = DIRECTORY_SEPARATOR === '\\'
            ? base_path('.venv\\Scripts\\python.exe')
            : base_path('.venv/bin/python');

        if (is_file($venvPython)) {
            return $venvPython;
        }

        return 'python';
    }
}
