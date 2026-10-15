<?php

use App\Http\Middleware\RejectDemoUsers;
use App\Mcp\Servers\ContabilidadServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Metadatos OAuth (.well-known) y registro dinámico de clientes para el
// conector de claude.ai; autorización y emisión de tokens las hace Passport.
Route::middleware('throttle:20,1')->group(fn () => Mcp::oauthRoutes());

// Servidor MCP de la cuenta dueña del token: token personal del Perfil
// (Claude Code / Desktop) o token OAuth (conector de claude.ai).
Mcp::web('/mcp', ContabilidadServer::class)
    ->middleware(['auth:mcp-token,api', RejectDemoUsers::class, 'throttle:120,1']);
