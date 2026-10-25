<div>
    <x-page-header title="Transferir entre contabilidades" route="/transfers · TransferBetweenBooks" />

    <div class="flex flex-col gap-4 p-4 sm:p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif

        <p class="max-w-[75ch] text-sm text-neutral-700">
            Mové dinero entre tus contabilidades —por ejemplo, un aporte de tu Binance personal al fondo familiar— y quedan
            registrados los dos asientos a la vez, enlazados. Si revertís uno, se revierten ambos.
        </p>

        @if ($books->count() < 2)
            <div class="panel p-6 text-sm">
                Necesitás al menos dos contabilidades.
                <a href="{{ route('books.index') }}" wire:navigate class="font-extrabold text-accent">Creá una compartida →</a>
            </div>
        @else
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Origen --}}
                    <section class="panel flex flex-col gap-3 p-4">
                        <div class="kicker">Sale de</div>
                        <div>
                            <x-input-label value="Contabilidad" />
                            <select wire:model.live="fromBookId" class="wf-input mt-1.5">
                                @foreach ($books as $b)
                                    <option value="{{ $b->id }}">{{ $b->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Cuenta de salida (efectivo)" />
                            <select wire:model="fromCash" class="wf-input mt-1.5">
                                <option value="">— Elegir —</option>
                                @foreach ($fromCashAccounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('fromCash')" class="mt-1" />
                            <x-input-error :messages="$errors->get('from_cash')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Contrapartida" />
                            <select wire:model="fromCounter" class="wf-input mt-1.5">
                                <option value="">Automática ({{ $from && ! $from->isPersonal() ? 'tus aportes' : 'Aportes a '.($to?->label() ?? '…') }})</option>
                                @foreach ($fromAccounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('from_counter')" class="mt-1" />
                        </div>
                    </section>

                    {{-- Destino --}}
                    <section class="panel flex flex-col gap-3 p-4">
                        <div class="kicker">Entra en</div>
                        <div>
                            <x-input-label value="Contabilidad" />
                            <select wire:model.live="toBookId" class="wf-input mt-1.5">
                                @foreach ($books as $b)
                                    <option value="{{ $b->id }}">{{ $b->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('fromBookId')" class="mt-1" />
                            <x-input-error :messages="$errors->get('to')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Cuenta de entrada (efectivo)" />
                            <select wire:model="toCash" class="wf-input mt-1.5">
                                <option value="">— Elegir —</option>
                                @foreach ($toCashAccounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('toCash')" class="mt-1" />
                            <x-input-error :messages="$errors->get('to_cash')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Contrapartida" />
                            <select wire:model="toCounter" class="wf-input mt-1.5">
                                <option value="">Automática ({{ $to && ! $to->isPersonal() ? 'tus aportes' : 'Reintegros de '.($from?->label() ?? '…') }})</option>
                                @foreach ($toAccounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('to_counter')" class="mt-1" />
                        </div>
                    </section>
                </div>

                <section class="panel grid gap-3 p-4 sm:grid-cols-[160px_160px_minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <x-input-label for="tr-amount" value="Monto" />
                        <x-text-input id="tr-amount" class="mt-1.5 text-right font-mono" wire:model="amount" inputmode="decimal" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tr-date" value="Fecha" />
                        <x-text-input id="tr-date" type="date" class="mt-1.5" wire:model="date" />
                        <x-input-error :messages="$errors->get('date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tr-desc" value="Descripción" />
                        <x-text-input id="tr-desc" class="mt-1.5" wire:model="description" placeholder="Aporte de octubre" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                    <x-primary-button class="justify-center">Registrar transferencia</x-primary-button>
                </section>

                <p class="text-xs text-neutral-600">
                    Se registra en «{{ $from?->label() }}»: Debe contrapartida / Haber cuenta de salida; y en «{{ $to?->label() }}»:
                    Debe cuenta de entrada / Haber contrapartida. En contabilidades compartidas, la contrapartida automática es tu
                    cuenta «Aportes de {{ auth()->user()->name }}», así se ve cuánto aportó cada uno.
                </p>
            </form>
        @endif
    </div>
</div>
