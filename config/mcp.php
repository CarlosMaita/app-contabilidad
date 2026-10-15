<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Dominios permitidos como redirect URI al registrar clientes OAuth
    | (registro dinámico en /oauth/register). Solo Claude: conectores de
    | claude.ai y los callbacks locales de Claude Code / Desktop.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'http://localhost',
        'http://127.0.0.1',
    ],

    'custom_schemes' => [],

    'authorization_server' => null,

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    'clients' => [],

];
