<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Schnellaktionen
        </x-slot>

        <div class="flex flex-wrap gap-4">
            <x-filament::button
                :href="$this->getCreateClientUrl()"
                tag="a"
                icon="heroicon-o-user-plus"
            >
                Neuer Kunde
            </x-filament::button>

            <x-filament::button
                :href="$this->getCreateProjectUrl()"
                tag="a"
                icon="heroicon-o-folder-plus"
                color="success"
            >
                Neues Projekt
            </x-filament::button>

            <x-filament::button
                :href="$this->getCreateInvoiceUrl()"
                tag="a"
                icon="heroicon-o-document-plus"
                color="warning"
            >
                Neue Rechnung
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
