<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        $user = Auth::guard('web')->user();

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        // Las cuentas demo son descartables: se borran al salir.
        if ($user?->is_demo && $user->exists) {
            $user->delete();
        }
    }
}
