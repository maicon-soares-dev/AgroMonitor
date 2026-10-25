<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RelatorioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** GET /api/dashboard?periodo=3|6|12|todos (padrão 6) — período do gráfico de plantios por mês. */
    public function __invoke(Request $request, RelatorioService $relatorios): JsonResponse
    {
        return $this->dados($relatorios->dashboard((string) $request->query('periodo', '6')));
    }
}
