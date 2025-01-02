<?php

use App\Models\User;
use App\Models\Document;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $selectedMonth = '';

    #[Url]
    public string $selectedYear = '';

    #[Url]
    public string $selectedStatus = '';

    #[Url]
    public ?int $selectedDepartment = null;
    
    public User $user;
    public array $months = [];
    public array $years = [];
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public function mount()
    {
        $this->user = auth()->user();

        // Generate months array
        $this->months = collect(range(1, 12))
            ->map(function ($month) {
                return [
                    'id' => $month,
                    'name' => date('F', mktime(0, 0, 0, $month, 1)),
                ];
            })
            ->all();

        // Generate years (last 5 years)
        $currentYear = date('Y');
        $this->years = collect(range($currentYear - 4, $currentYear))
            ->map(function ($year) {
                return [
                    'id' => $year,
                    'name' => (string) $year,
                ];
            })
            ->all();

        // Set default filters to current month and year
        $this->selectedMonth = date('n');
        $this->selectedYear = date('Y');
    }

    public function getDocumentQuery(): Builder
    {
        $query = Document::query();

        // Apply role-based filtering
        switch ($this->user->role) {
            case 'superadmin':
            case 'admin':
                // Can see all documents
                break;
            case 'pimpinan':
                // Can see documents from their department
                $query->whereHas('creator', function ($q) {
                    $q->where('department_id', $this->user->department_id);
                });
                break;
            default:
                // Regular users can only see their own documents
                $query->where('created_by', $this->user->id);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('document_number', 'like', "%{$this->search}%")
                    ->orWhereHas('creator', function ($userQuery) {
                        $userQuery->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        // Apply date filters
        if ($this->selectedMonth && $this->selectedYear) {
            $query->whereMonth('document_date', $this->selectedMonth)->whereYear('document_date', $this->selectedYear);
        }

        // Apply status filter
        if ($this->selectedStatus) {
            $query->where('status', $this->selectedStatus);
        }

        // Apply department filter (for admins/superadmins)
        if ($this->selectedDepartment && in_array($this->user->role, ['admin', 'superadmin'])) {
            $query->whereHas('creator', function ($q) {
                $q->where('department_id', $this->selectedDepartment);
            });
        }

        return $query;
    }

    public function clearFilters()
    {
        $this->selectedMonth = date('n');
        $this->selectedYear = date('Y');
        $this->selectedStatus = '';
        $this->selectedDepartment = null;
        $this->search = '';
    }

    public function headers(): array
    {
        return [['key' => 'document_number', 'label' => 'Nomor Dokumen', 'sortable' => true], ['key' => 'title', 'label' => 'Judul', 'sortable' => true], ['key' => 'creator', 'label' => 'Dibuat Oleh'], ['key' => 'department', 'label' => 'Departemen'], ['key' => 'document_date', 'label' => 'Tanggal', 'sortable' => true], ['key' => 'status', 'label' => 'Status'], ['key' => 'actions', 'label' => 'Aksi']];
    }

    public function with(): array
    {
        return [
            'documents' => $this->getDocumentQuery()
                ->with(['creator.department', 'documentType'])
                ->orderBy(...array_values($this->sortBy))
                ->paginate(10),
            'headers' => $this->headers(),
            'departments' => Department::all(),
            'statuses' => [['id' => 'draft', 'name' => 'Draft'], ['id' => 'waiting_approval', 'name' => 'Menunggu Persetujuan'], ['id' => 'waiting_first_approval', 'name' => 'Menunggu Persetujuan Pertama'], ['id' => 'waiting_final_approval', 'name' => 'Menunggu Persetujuan Akhir'], ['id' => 'approved', 'name' => 'Disetujui'], ['id' => 'signed', 'name' => 'Ditandatangani'], ['id' => 'archived', 'name' => 'Diarsipkan'], ['id' => 'rejected', 'name' => 'Ditolak']],
        ];
    }
};
?>

<div>
    <!-- Header -->
    <x-header title="Daftar Dokumen" separator>
        <x-slot:actions>
            <x-input placeholder="Cari dokumen..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:actions>
    </x-header>

    <!-- Filter Section -->
    <x-card class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-select label="Bulan" wire:model.live="selectedMonth" :options="$months" placeholder="Pilih Bulan" />

            <x-select label="Tahun" wire:model.live="selectedYear" :options="$years" placeholder="Pilih Tahun" />

            <x-select label="Status" wire:model.live="selectedStatus" :options="$statuses" placeholder="Pilih Status" />

            @if (in_array($user->role, ['admin', 'superadmin']))
                <x-select label="Departemen" wire:model.live="selectedDepartment" :options="$departments" option-label="name"
                    option-value="id" placeholder="Pilih Departemen" />
            @endif
        </div>

        <div class="mt-4 flex justify-end">
            <x-button label="Reset Filter" wire:click="clearFilters" icon="o-x-mark" class="btn-ghost" />
        </div>
    </x-card>

    <!-- Documents Table -->
    <x-card>
        <x-table :headers="$headers" :rows="$documents" :sort-by="$sortBy" with-pagination>
            @scope('cell_creator', $document)
                {{ $document->creator->name }}
            @endscope

            @scope('cell_department', $document)
                {{ $document->creator->department->name ?? '-' }}
            @endscope

            @scope('cell_document_date', $document)
                {{ $document->document_date->format('d/m/Y') }}
            @endscope

            @scope('cell_status', $document)
                <x-badge :value="match ($document->status) {
                    'draft' => 'Draft',
                    'waiting_approval' => 'Menunggu Persetujuan',
                    'waiting_first_approval' => 'Menunggu Persetujuan Pertama',
                    'waiting_final_approval' => 'Menunggu Persetujuan Akhir',
                    'approved' => 'Disetujui',
                    'signed' => 'Ditandatangani',
                    'archived' => 'Diarsipkan',
                    'rejected' => 'Ditolak',
                    default => ucfirst($document->status),
                }" :color="match ($document->status) {
                    'draft' => 'gray',
                    'waiting_approval', 'waiting_first_approval', 'waiting_final_approval' => 'warning',
                    'approved', 'signed', 'archived' => 'success',
                    'rejected' => 'error',
                    default => 'info',
                }" />
            @endscope

            @scope('actions', $document)
                <div class="flex space-x-2">
                    <x-button icon="o-eye" link="{{ route('documents.show', $document->id) }}" class="btn-ghost btn-sm"
                        tooltip="Lihat Detail" />
                    @if ($document->file_path)
                            <x-button icon="o-arrow-down-tray" 
                            link="{{ route('documents.download', $document->id) }}" 
                            external
                            class="btn-ghost btn-sm"
                            tooltip="Unduh Dokumen" />
                    @endif
                    @if ($document->signed_file_path)
                            <x-button icon="o-arrow-down-tray" 
                            link="{{ route('documents.download-signed', $document->id) }}" 
                            external
                            class="btn-ghost btn-sm"
                            tooltip="Unduh Dokumen Signed" />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
