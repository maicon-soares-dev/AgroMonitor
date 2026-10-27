<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /** {"sucesso": true, "dados": ...} */
    protected function dados(mixed $dados, int $status = 200): JsonResponse
    {
        return response()->json(['sucesso' => true, 'dados' => $dados], $status);
    }

    /** {"sucesso": true, "id": ...} — resposta de criação */
    protected function criado(int|string $id): JsonResponse
    {
        return response()->json(['sucesso' => true, 'id' => $id]);
    }

    /** {"sucesso": true} — resposta de alteração/exclusão */
    protected function ok(): JsonResponse
    {
        return response()->json(['sucesso' => true]);
    }

    /** {"sucesso": false, "erro": "..."} */
    protected function erro(string $mensagem, int $status = 400): JsonResponse
    {
        return response()->json(['sucesso' => false, 'erro' => $mensagem], $status);
    }
}
