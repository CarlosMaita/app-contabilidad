<?php

namespace App\Http\Controllers;

use App\Demo\DemoSandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /** «Ver como demo»: crea una cuenta descartable con datos de ejemplo y entra. */
    public function store(Request $request, DemoSandbox $sandbox): RedirectResponse
    {
        $sandbox->purgeExpired();

        Auth::login($sandbox->create());
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
