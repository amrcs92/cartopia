<?php

namespace App\Filament\Resources\Aisles\Pages;

use App\Filament\Resources\Aisles\AisleResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditAisle extends EditRecord
{
    protected static string $resource = AisleResource::class;

    private ?string $previousTile = null;

    protected function getHeaderActions(): array
    {
        return [AisleResource::deleteAction()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousTile = $this->getRecord()->getAttribute('image_path');

        return $data;
    }

    // A replaced or removed tile no longer has a use, so its file goes.
    protected function afterSave(): void
    {
        $current = $this->getRecord()->getAttribute('image_path');

        if ($this->previousTile !== null && $this->previousTile !== $current) {
            Storage::disk('public')->delete($this->previousTile);
        }
    }
}
