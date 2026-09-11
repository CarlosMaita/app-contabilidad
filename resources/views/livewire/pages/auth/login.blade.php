<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-4">
    <div class="flex border border-neutral-400">
        <span class="flex-1 bg-ink px-3.5 py-2 text-left text-[13px] font-extrabold text-ground">Ingresar</span>
        <a href="{{ route('register') }}" wire:navigate
           class="flex-1 border-l border-neutral-400 px-3.5 py-2 text-left text-[13px] font-extrabold hover:bg-neutral-200">Registrarse</a>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-2" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo" />
            <x-text-input wire:model="form.email" id="email" class="mt-1.5" type="email" name="email" required autofocus autocomplete="username" placeholder="tu@correo.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input wire:model="form.password" id="password" class="mt-1.5"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember" class="inline-flex items-center gap-2 text-sm text-neutral-700">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                   class="border-neutral-400 text-accent focus:ring-accent" style="border-radius: 0;">
            Recordarme
        </label>

        <x-primary-button class="w-full">Ingresar</x-primary-button>

        @if (Route::has('password.request'))
            <a class="text-xs text-neutral-700 hover:text-accent" href="{{ route('password.request') }}" wire:navigate>
                ¿Olvidaste tu contraseña?
            </a>
        @endif
    </form>
</div>
