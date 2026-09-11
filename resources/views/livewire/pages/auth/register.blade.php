<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-4">
    <div class="flex border border-neutral-400">
        <a href="{{ route('login') }}" wire:navigate
           class="flex-1 px-3.5 py-2 text-left text-[13px] font-extrabold hover:bg-neutral-200">Ingresar</a>
        <span class="flex-1 border-l border-neutral-400 bg-ink px-3.5 py-2 text-left text-[13px] font-extrabold text-ground">Registrarse</span>
    </div>

    <form wire:submit="register" class="flex flex-col gap-4">
        <!-- Name -->
        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input wire:model="name" id="name" class="mt-1.5" type="text" name="name" required autofocus autocomplete="name" placeholder="Nombre y apellido" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo" />
            <x-text-input wire:model="email" id="email" class="mt-1.5" type="email" name="email" required autocomplete="username" placeholder="tu@correo.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input wire:model="password" id="password" class="mt-1.5"
                            type="password"
                            name="password"
                            required autocomplete="new-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" value="Repetir contraseña" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-1.5"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Crear cuenta</x-primary-button>

        <div class="text-xs text-neutral-700">
            Se envía un correo de verificación; sin verificar no se puede operar.
        </div>
    </form>
</div>
