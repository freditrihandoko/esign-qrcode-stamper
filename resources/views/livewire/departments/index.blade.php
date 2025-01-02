<?php

use App\Models\Department;
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
    public bool $myModal = false;
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public ?Department $selectedDepartment = null;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

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

    public function saveDepartment()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:departments,code' . ($this->selectedDepartment ? ",{$this->selectedDepartment->id}" : ''),
            'description' => 'nullable|string',
        ]);

        if ($this->selectedDepartment) {
            $this->selectedDepartment->update([
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
            ]);
            $this->success('Department updated successfully.');
        } else {
            Department::create([
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
            ]);
            $this->success('Department created successfully.');
        }

        $this->myModal = false;
        $this->clear();
    }

    public function edit(Department $department): void
    {
        $this->selectedDepartment = $department;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->description = $department->description;
        $this->myModal = true;
    }

    public function delete(Department $department): void
    {
        // try {
        //     $department->delete();
        //     $this->warning("$department->name deleted successfully.", position: 'toast-bottom');
        // } catch (\Exception $e) {
        //     $this->error('An error occurred while deleting department: ' . $e->getMessage());
        // }
        try {
            // Check if department is being used by any users
            $usersCount = User::where('department_id', $department->id)->count();
            
            if ($usersCount > 0) {
                $this->error("Cannot delete department '{$department->name}' because it is being used by {$usersCount} user(s).");
                return;
            }

            $department->delete();
            $this->warning("$department->name deleted successfully.", position: 'toast-bottom');
        } catch (\Exception $e) {
            $this->error('An error occurred while deleting department: ' . $e->getMessage());
        }
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name', 'class' => 'w-64'],
            ['key' => 'code', 'label' => 'Code', 'class' => 'w-32'],
            ['key' => 'description', 'label' => 'Description', 'sortable' => false],
        ];
    }

    public function departments(): LengthAwarePaginator
    {
        return Department::query()
            ->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%$this->search%"))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(5);
    }

    public function with(): array
    {
        return [
            'departments' => $this->departments(),
            'headers' => $this->headers(),
        ];
    }
};
?>

<div>
    <!-- HEADER -->
    <x-header title="Departments" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create Department" @click="$wire.myModal = true" responsive icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE -->
    <x-card>
        <x-table :headers="$headers" :rows="$departments" :sort-by="$sortBy" with-pagination>
            @scope('cell_name', $department)
                <strong>{{ $department->name }}</strong>
            @endscope
            @scope('cell_code', $department)
                <code>{{ $department->code }}</code>
            @endscope
            @scope('actions', $department)
                <x-button icon="o-pencil" wire:click="edit({{ $department['id'] }})" class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $department['id'] }})" wire:confirm="Are you sure you want to delete this department? This action cannot be undone." spinner class="btn-ghost btn-sm text-red-500" />
            @endscope
        </x-table>
    </x-card>

    <!-- MODAL -->
    <x-modal wire:model="myModal" title="{{ $selectedDepartment ? 'Edit Department' : 'Create New Department' }}" separator>
        <div class="grid gap-5">
            <x-input label="Name" wire:model.live="name" placeholder="Enter department name" />
            <x-input label="Code" wire:model.live="code" placeholder="Enter department code" />
            <x-textarea label="Description" wire:model.live="description" placeholder="Enter department description" />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" @click="$wire.clear(); $wire.myModal = false" />
            <x-button label="{{ $selectedDepartment ? 'Update' : 'Create' }}" wire:click="saveDepartment" class="btn-primary" spinner />
        </x-slot:actions>
    </x-modal>
</div>
