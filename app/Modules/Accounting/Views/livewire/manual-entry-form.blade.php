<div>
    <x-page-header title="Asiento manual" route="/journal/new · ManualEntryForm" />

    <div class="flex flex-col gap-4 p-6">
        <div>
            <a href="{{ route('journal.index') }}" wire:navigate class="text-xs text-accent hover:text-accent-700">← Libro diario</a>
            <p class="mt-1 max-w-[70ch] text-sm text-neutral-700">
                Asiento de doble partida sin operación de origen (ajustes, aperturas, correcciones).
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-4">
            <div class="panel grid grid-cols-1 gap-4 p-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="me-date" value="Fecha contable" />
                    <x-text-input id="me-date" type="date" class="mt-1.5" wire:model="date" />
                    <x-input-error :messages="$errors->get('date')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="me-desc" value="Descripción" />
                    <x-text-input id="me-desc" class="mt-1.5" wire:model="description" placeholder="Asiento de apertura" />
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>

            @error('lines')
                <div class="note-accent text-sm font-extrabold text-accent-600">{{ $message }}</div>
            @enderror

            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Líneas</span>
                    <div class="ml-auto flex gap-2">
                        <x-secondary-button wire:click="addLine('debit')">+ Debe</x-secondary-button>
                        <x-secondary-button wire:click="addLine('credit')">+ Haber</x-secondary-button>
                    </div>
                </div>

                <div class="flex flex-col">
                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-12 items-start gap-2 border-b border-neutral-200 px-4 py-3" wire:key="mline-{{ $i }}">
                            <div class="col-span-6 sm:col-span-2">
                                <select wire:model.live="lines.{{ $i }}.side" class="wf-input">
                                    <option value="debit">Debe</option>
                                    <option value="credit">Haber</option>
                                </select>
                            </div>
                            <div class="col-span-6 sm:col-span-4">
                                <select wire:model="lines.{{ $i }}.account_id" class="wf-input">
                                    <option value="">— Cuenta —</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('lines.'.$i.'.account_id')" class="mt-1" />
                            </div>
                            <div class="col-span-5 sm:col-span-2">
                                <x-text-input type="number" step="0.01" min="0" placeholder="0.00" class="text-right font-mono"
                                              wire:model.live.debounce.400ms="lines.{{ $i }}.amount" />
                                <x-input-error :messages="$errors->get('lines.'.$i.'.amount')" class="mt-1" />
                            </div>
                            <div class="col-span-5 sm:col-span-3">
                                <x-text-input placeholder="Memo (opcional)" wire:model="lines.{{ $i }}.memo" />
                            </div>
                            <div class="col-span-2 pt-1.5 text-right sm:col-span-1">
                                <button type="button" class="btn-ghost text-xs" wire:click="removeLine({{ $i }})">Quitar</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-[13px]">
                    <span class="font-extrabold {{ $balanced ? 'text-ink' : 'text-accent-600' }}">
                        {{ $balanced ? 'El asiento balancea.' : 'Debe y Haber deben ser iguales y mayores a cero.' }}
                    </span>
                    <span class="font-mono">
                        Debe <span class="font-extrabold">{{ number_format((float) $totalDebit, 2, ',', '.') }}</span>
                        · Haber <span class="font-extrabold">{{ number_format((float) $totalCredit, 2, ',', '.') }}</span>
                    </span>
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">Registrar asiento</x-primary-button>
            </div>
        </form>
    </div>
</div>
