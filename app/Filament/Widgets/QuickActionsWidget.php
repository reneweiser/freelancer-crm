<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.quick-actions-widget';

    protected int|string|array $columnSpan = 'full';

    public function getCreateClientUrl(): string
    {
        return ClientResource::getUrl('create');
    }

    public function getCreateProjectUrl(): string
    {
        return ProjectResource::getUrl('create');
    }

    public function getCreateInvoiceUrl(): string
    {
        return InvoiceResource::getUrl('create');
    }
}
