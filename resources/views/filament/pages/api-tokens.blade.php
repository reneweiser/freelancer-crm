<x-filament-panels::page>
    @if($this->newTokenPlainText)
        <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 mb-6 border border-warning-200 dark:border-warning-800">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-500 flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                        Neuer API-Token erstellt
                    </h3>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                        Kopieren Sie diesen Token jetzt. Er wird aus Sicherheitsgründen nicht erneut angezeigt.
                    </p>
                    <div class="mt-3">
                        <div class="flex items-center gap-2">
                            <code class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 rounded border border-warning-300 dark:border-warning-700 text-sm font-mono break-all select-all">
                                {{ $this->newTokenPlainText }}
                            </code>
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText('{{ $this->newTokenPlainText }}')"
                                class="px-3 py-2 bg-warning-100 dark:bg-warning-800 hover:bg-warning-200 dark:hover:bg-warning-700 rounded text-warning-800 dark:text-warning-200 transition-colors"
                                title="In Zwischenablage kopieren"
                            >
                                <x-heroicon-o-clipboard class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button
                            type="button"
                            wire:click="dismissToken"
                            class="text-sm text-warning-600 dark:text-warning-400 hover:text-warning-800 dark:hover:text-warning-200 underline"
                        >
                            Token wurde kopiert, Meldung ausblenden
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">
                    Aktive Tokens
                </x-slot>
                <x-slot name="description">
                    Verwalten Sie Ihre API-Tokens. Tokens ermöglichen den programmatischen Zugriff auf Ihre CRM-Daten.
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>

        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Einrichtung
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($this->getSetupInstructions()) !!}
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Sicherheitshinweise
                </x-slot>

                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-shield-check class="h-5 w-5 text-success-500 flex-shrink-0 mt-0.5" />
                        <span>Tokens haben vollen Zugriff auf Ihre Daten. Teilen Sie sie nicht.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-clock class="h-5 w-5 text-info-500 flex-shrink-0 mt-0.5" />
                        <span>Widerrufen Sie ungenutzte Tokens regelmäßig.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-eye-slash class="h-5 w-5 text-warning-500 flex-shrink-0 mt-0.5" />
                        <span>Speichern Sie Tokens sicher in Umgebungsvariablen.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-arrow-path class="h-5 w-5 text-gray-500 flex-shrink-0 mt-0.5" />
                        <span>Rotieren Sie Tokens bei Verdacht auf Kompromittierung.</span>
                    </li>
                </ul>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
