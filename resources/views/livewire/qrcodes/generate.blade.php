<?php

use App\Models\QrCodeGeneration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    use WithPagination;

    public string $search = '';
    public bool $myModal = false;
    public ?string $qr_generation_code = null;
    public ?array $generation_details = null;
    public ?QrCodeGeneration $selectedQrCodeGeneration = null;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
    }

    public function getActiveFiltersCountProperty(): int
    {
        return !empty($this->search) ? 1 : 0;
    }

    public function updated($property): void
    {
        if (!is_array($property) && $property != '') {
            $this->resetPage();
        }
    }

    public function saveQrCodeGeneration()
    {
        $this->validate([
            'qr_generation_code' => 'required|string|unique:qr_code_generations,qr_generation_code' . ($this->selectedQrCodeGeneration ? ",{$this->selectedQrCodeGeneration->id}" : ''),
            'generation_details' => 'nullable|array',
        ]);

        // Prepare generation details as JSON
        $generationDetailsJson = $this->generation_details ? json_encode($this->generation_details) : null;

        try {
            if ($this->selectedQrCodeGeneration) {
                // Update existing
                $this->selectedQrCodeGeneration->update([
                    'qr_generation_code' => $this->qr_generation_code,
                    'generation_details' => $generationDetailsJson,
                ]);
                $this->success('QR Code Generation updated successfully.');
            } else {
                // Create new
                QrCodeGeneration::create([
                    'qr_generation_code' => $this->qr_generation_code,
                    'generation_details' => $generationDetailsJson,
                    'created_by' => auth()->id(), // Assuming you want to track the creator
                ]);
                $this->success('QR Code Generation created successfully.');
            }

            $this->myModal = false;
            $this->clear();
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }

    public function edit(QrCodeGeneration $qrCodeGeneration): void
    {
        $this->selectedQrCodeGeneration = $qrCodeGeneration;
        $this->qr_generation_code = $qrCodeGeneration->qr_generation_code;
        $this->generation_details = $qrCodeGeneration->generation_details ? json_decode($qrCodeGeneration->generation_details, true) : null;
        $this->myModal = true;
    }

    public function delete(QrCodeGeneration $qrCodeGeneration): void
    {
        try {
            $qrCodeGeneration->delete();
            $this->warning("QR Code Generation {$qrCodeGeneration->qr_generation_code} deleted successfully.", position: 'toast-bottom');
        } catch (\Exception $e) {
            $this->error('An error occurred while deleting QR Code Generation: ' . $e->getMessage());
        }
    }

    public function headers(): array
    {
        return [['key' => 'id', 'label' => '#', 'class' => 'w-1'], ['key' => 'qr_generation_code', 'label' => 'Generation Code', 'class' => 'w-64'], ['key' => 'is_used', 'label' => 'Used', 'sortable' => false, 'class' => 'w-16'], ['key' => 'created_by', 'label' => 'Created By', 'class' => 'w-32'], ['key' => 'created_at', 'label' => 'Created At', 'sortable' => true]];
    }

    public function qrCodeGenerations(): LengthAwarePaginator
    {
        return QrCodeGeneration::query()
            // ->with(['creator', 'qrCodes'])
            ->with('creator')
            ->when($this->search, fn(Builder $q) => $q->where('qr_generation_code', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(5);
    }

    public function with(): array
    {
        return [
            'qrCodeGenerations' => $this->qrCodeGenerations(),
            'headers' => $this->headers(),
        ];
    }

    // Optional method to dynamically generate a QR Code generation code
    public function generateCode(): void
    {
        $this->qr_generation_code = 'QRG-' . strtoupper(uniqid());
    }
};
?>

<div>
    <!-- HEADER -->
    <x-header title="QR Code Generations" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create QR Code Generation" @click="$wire.myModal = true; $wire.generateCode()" responsive
                icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE -->
    <x-card>
        <x-table :headers="$headers" :rows="$qrCodeGenerations" :sort-by="$sortBy" with-pagination>
            @scope('cell_qr_generation_code', $generation)
                <code>{{ $generation->qr_generation_code }}</code>
            @endscope

            @scope('cell_is_used', $generation)
                @if ($generation->isUsed())
                    <x-icon name="o-check" class="text-green-500" />
                @else
                    <x-icon name="o-x-mark" class="text-red-500" />
                @endif
            @endscope

            @scope('cell_created_by', $generation)
                {{ $generation->creator->name ?? 'Unknown' }}
            @endscope

            @scope('cell_created_at', $generation)
                {{ $generation->created_at->format('Y-m-d H:i') }}
            @endscope

            @scope('actions', $generation)
                <x-button icon="o-pencil" wire:click="edit({{ $generation['id'] }})" class="btn-ghost btn-sm" />
                @unless ($generation->isUsed())
                    <x-button icon="o-trash" wire:click="delete({{ $generation['id'] }})"
                        wire:confirm="Are you sure you want to delete this QR Generation? This action cannot be undone." spinner
                        class="btn-ghost btn-sm text-red-500" />
                @endunless
            @endscope
        </x-table>
    </x-card>

    <!-- MODAL -->
    <x-modal wire:model="myModal"
        title="{{ $selectedQrCodeGeneration ? 'Edit QR Code Generation' : 'Create New QR Code Generation' }}" separator>
        <div class="grid gap-5">
            <x-input label="Generation Code" wire:model.live="qr_generation_code" placeholder="QR Generation Code"
                readonly hint="Auto-generated code" />

            <x-textarea label="Generation Details (JSON)" wire:model.live="generation_details"
                placeholder="Optional JSON metadata for generation details" />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" @click="$wire.clear(); $wire.myModal = false" />
            <x-button label="{{ $selectedQrCodeGeneration ? 'Update' : 'Create' }}" wire:click="saveQrCodeGeneration"
                class="btn-primary" spinner />
        </x-slot:actions>
    </x-modal>
</div>
