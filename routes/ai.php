<?php

use App\Mcp\Servers\ContabilidadServer;
use Laravel\Mcp\Facades\Mcp;

// Servidor MCP de la cuenta dueña del token (Bearer de Sanctum, se genera en el Perfil).
Mcp::web('/mcp', ContabilidadServer::class)
    ->middleware(['auth:sanctum', 'throttle:120,1']);
