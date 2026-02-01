<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-bell class="h-5 w-5" />
                Anstehende Erinnerungen
            </div>
        </x-slot>

        @if($this->getReminders()->isEmpty())
            <div class="text-center py-4">
                <x-heroicon-o-bell-slash class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500">Keine anstehenden Erinnerungen.</p>
                <x-filament::button
                    :href="$this->getCreateUrl()"
                    tag="a"
                    size="sm"
                    color="gray"
                    class="mt-3"
                >
                    Erste Erinnerung erstellen
                </x-filament::button>
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($this->getReminders() as $reminder)
                    <li class="py-3 flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium truncate {{ $reminder->is_overdue ? 'text-danger-600' : '' }}">
                                    {{ $reminder->title }}
                                </p>
                                @if($reminder->is_system)
                                    <x-filament::badge size="sm" color="gray">
                                        Auto
                                    </x-filament::badge>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $reminder->due_at->format('d.m.Y H:i') }}
                                @if($reminder->remindable)
                                    &middot; {{ class_basename($reminder->remindable) }}
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <x-filament::icon-button
                                icon="heroicon-o-check"
                                color="success"
                                size="sm"
                                wire:click="completeReminder({{ $reminder->id }})"
                                label="Erledigt"
                            />
                            <x-filament::icon-button
                                icon="heroicon-o-clock"
                                color="gray"
                                size="sm"
                                wire:click="snoozeReminder({{ $reminder->id }})"
                                label="Später erinnern"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">
                <x-filament::link :href="$this->getAllRemindersUrl()">
                    Alle Erinnerungen anzeigen &rarr;
                </x-filament::link>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
