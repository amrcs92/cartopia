<?php

namespace App\Filament\Resources\Aisles\Pages;

use App\Filament\Resources\Aisles\AisleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAisles extends ListRecords
{
    protected static string $resource = AisleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
