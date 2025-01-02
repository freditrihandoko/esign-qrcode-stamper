<?php

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    use WithPagination;

    public string $search = '';
    public bool $requires_approval = false;
    public bool $myModal = false;
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public ?int $default_first_approver_id = null;
    public ?int $default_final_approver_id = null;
    public ?DocumentType $selectedDocumentType = null;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // Ambil daftar users untuk dropdown approver
    public function getUsersProperty()
    {
        return User::whereIn('role', ['admin', 'pimpinan', 'approver'])->get();
    }

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->resetPage();
    }

    public function getActiveFiltersCountProperty()
    {
        $count = 0;

        if (!empty($this->search)) {
            $count++;
        }

        return $count;
    }

    public function updated($property): void
    {
        if (!is_array($property) && $property != '') {
            $this->resetPage();
        }
    }

    // Save or update document type
    public function saveDocumentType()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:document_types,code' . ($this->selectedDocumentType ? ",{$this->selectedDocumentType->id}" : ''),
            'description' => 'nullable|string',
            'requires_approval' => 'boolean',
            'default_first_approver_id' => 'nullable|exists:users,id',
            'default_final_approver_id' => 'nullable|exists:users,id',
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'requires_approval' => $this->requires_approval,
            'default_first_approver_id' => $this->default_first_approver_id,
            'default_final_approver_id' => $this->default_final_approver_id,
        ];

        if ($this->selectedDocumentType) {
            $this->selectedDocumentType->update($data);
            $this->success('Document Type updated successfully.');
        } else {
            DocumentType::create($data);
            $this->success('Document Type created successfully.');
        }

        $this->myModal = false;
        $this->clear();
    }

    public function edit(DocumentType $documentType): void
    {
        $this->selectedDocumentType = $documentType;
        $this->name = $documentType->name;
        $this->code = $documentType->code;
        $this->description = $documentType->description;
        $this->requires_approval = $documentType->requires_approval;
        $this->default_first_approver_id = $documentType->default_first_approver_id;
        $this->default_final_approver_id = $documentType->default_final_approver_id;
        $this->myModal = true;
    }

    public function delete(DocumentType $documentType): void
    {
        if ($documentType->documents()->exists()) {
            $this->error('Cannot delete document type as it is associated with documents.');
            return;
        }

        try {
            $documentType->delete();
            $this->warning("{$documentType->name} deleted successfully.");
        } catch (\Exception $e) {
            $this->error('An error occurred while deleting the document type: ' . $e->getMessage());
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'requires_approval', 'label' => 'Approval Required', 'class' => 'hidden lg:table-cell'],
        ];
    }

    public function documentTypes(): LengthAwarePaginator
    {
        return DocumentType::query()
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(5);
    }

    public function with(): array
    {
        return [
            'documentTypes' => $this->documentTypes(),
            'headers' => $this->headers(),
            'users' => $this->users,
        ];
    }
};
?>

<div>
    <!-- HEADER -->
    <x-header title="Document Types" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create Document Type" @click="$wire.myModal = true" responsive icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE -->
    <x-card>
        <x-table :headers="$headers" :rows="$documentTypes" :sort-by="$sortBy" with-pagination>
            @scope('cell_requires_approval', $documentType)
                <x-icon :name="$documentType['requires_approval'] ? 'o-check-circle' : 'o-x-circle'" :class="$documentType['requires_approval'] ? 'h-6 w-6 text-green-500' : 'h-6 w-6 text-red-500'" :label="$documentType['requires_approval'] ? 'Yes' : 'No'" />
            @endscope
            @scope('actions', $documentType)
                <x-button icon="o-pencil" wire:click="edit({{ $documentType['id'] }})" class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $documentType['id'] }})" wire:confirm="Are you sure?" spinner class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>
    </x-card>

    <!-- MODAL -->
    <x-modal wire:model="myModal" title="{{ $selectedDocumentType ? 'Edit Document Type' : 'Create New Document Type' }}" separator>
        <div class="grid gap-5">
            <x-input label="Name" wire:model.live="name" placeholder="Enter document type name" />
            <x-input label="Code" wire:model.live="code" placeholder="Enter unique initial code" hint="digunakan untuk inisial kode di nomor surat, contoh SDIR, SKET, dll" />
            <x-textarea label="Description" wire:model.live="description" placeholder="Enter description" />
            <x-toggle label="Requires Approval" wire:model.live="requires_approval" />
            
            <!-- Tambahkan dropdown untuk default approver -->
            @if($requires_approval)
                <x-select 
                    label="Default First Approver" 
                    wire:model.live="default_first_approver_id" 
                    :options="$users" 
                    placeholder="Select First Approver"
                    option-value="id" 
                    option-label="name"
                />
                
                <x-select 
                    label="Default Final Approver" 
                    wire:model.live="default_final_approver_id" 
                    :options="$users" 
                    placeholder="Select Final Approver"
                    option-value="id" 
                    option-label="name"
                />
            @endif
        </div>

        <x-slot:actions>
            <x-button label="Cancel" @click="$wire.clear(); $wire.myModal = false" />
            <x-button label="{{ $selectedDocumentType ? 'Update' : 'Create' }}" wire:click="saveDocumentType" class="btn-primary" spinner />
        </x-slot:actions>
    </x-modal>
</div>