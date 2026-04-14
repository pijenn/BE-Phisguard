<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="PhishGuard API",
 *     version="1.0.0",
 *     description="API untuk deteksi dan pelaporan phishing"
 * )
 *
 * @OA\Server(
 *     url="https://be-phisguard-production.up.railway.app/api",
 *     description="Production Server"
 * )
 */
class ApiInfo {}