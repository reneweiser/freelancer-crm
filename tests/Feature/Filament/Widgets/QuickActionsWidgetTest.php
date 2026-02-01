<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Widgets\QuickActionsWidget;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('QuickActionsWidget', function () {
    it('can render', function () {
        Livewire::test(QuickActionsWidget::class)
            ->assertSuccessful();
    });

    it('displays section heading', function () {
        Livewire::test(QuickActionsWidget::class)
            ->assertSee('Schnellaktionen');
    });

    it('displays create client button', function () {
        Livewire::test(QuickActionsWidget::class)
            ->assertSee('Neuer Kunde');
    });

    it('displays create project button', function () {
        Livewire::test(QuickActionsWidget::class)
            ->assertSee('Neues Projekt');
    });

    it('displays create invoice button', function () {
        Livewire::test(QuickActionsWidget::class)
            ->assertSee('Neue Rechnung');
    });

    it('has correct URL for create client action', function () {
        $widget = new QuickActionsWidget;
        $url = $widget->getCreateClientUrl();

        expect($url)->toContain(ClientResource::getUrl('create'));
    });

    it('has correct URL for create project action', function () {
        $widget = new QuickActionsWidget;
        $url = $widget->getCreateProjectUrl();

        expect($url)->toContain(ProjectResource::getUrl('create'));
    });

    it('has correct URL for create invoice action', function () {
        $widget = new QuickActionsWidget;
        $url = $widget->getCreateInvoiceUrl();

        expect($url)->toContain(InvoiceResource::getUrl('create'));
    });
});
