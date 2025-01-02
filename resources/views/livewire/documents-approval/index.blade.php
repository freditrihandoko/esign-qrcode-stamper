<?php

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    #[Url]
    public string $search = '';

    public array $sortBy = ['column' => 'document_date', 'direction' => 'desc'];

    #[Url]
    public ?int $selectedDepartmentId = null;

    #[Url]
    public ?int $selectedDocumentTypeId = null;

    #[Url]
    public string $activeTab = 'waiting_approval';

    public bool $approvalModal = false;
    public bool $drawer = false;
    public int $selectedDocumentId = 0;

    #[Url(as: 'daterange')]
    public array $dateRange = [];
    
    public string $dateRangeString = '';

    public function mount(): void
    {
        if (empty($this->dateRange)) {
            $startDate = now()->subDays(7)->format('Y-m-d');
            $endDate = now()->addDay()->format('Y-m-d');

            $this->dateRange = [$startDate, $endDate];
            $this->dateRangeString = "$startDate to $endDate";
        }
    }

    public function updatedDateRangeString($value)
    {
        $this->dateRange = explode(' to ', $value);

        if (count($this->dateRange) == 2) {
            $start = \Carbon\Carbon::parse($this->dateRange[0]);
            $end = \Carbon\Carbon::parse($this->dateRange[1]);

            if ($start->diffInDays($end) > 31) {
                $this->dateRange = [$start->format('Y-m-d'), $start->copy()->addDays(31)->format('Y-m-d')];
                $this->dateRangeString = implode(' to ', $this->dateRange);
                $this->warning('Date range limited to 31 days maximum');
            }
        }
    }

    public function getActiveFiltersCountProperty(): int
    {
        $count = 0;
        if (!empty($this->search)) $count++;
        if ($this->selectedDepartmentId) $count++;
        if ($this->selectedDocumentTypeId) $count++;
        return $count;
    }

    public function getDocumentCountsProperty()
    {
        return [
            'waiting_approval' => Document::where('status', 'waiting_approval')->count(),
            'waiting_first_approval' => Document::where('status', 'waiting_first_approval')->count(),
            'waiting_final_approval' => Document::where('status', 'waiting_final_approval')->count(),
            'approved' => Document::where('status', 'approved')->count(),
            'signed' => Document::where('status', 'signed')->count(),
            'rejected' => Document::where('status', 'rejected')->count(),
            'archived' => Document::where('status', 'archived')->count(),
        ];
    }

    public function clear(): void
    {
        $this->search = '';
        $this->selectedDepartmentId = null;
        $this->selectedDocumentTypeId = null;
        
        $today = now()->format('Y-m-d');
        $this->dateRangeString = "$today to $today";
        $this->dateRange = [$today, $today];

        $this->resetPage();
    }

    public function updated($property): void
    {
        if (!is_array($property) && $property != '') {
            $this->resetPage();
        }
    }

    public function approveDocument()
    {
        $document = Document::findOrFail($this->selectedDocumentId);
        $documentType = DocumentType::find($document->document_type_id);
        $userRole = auth()->user()->role;

        $isAdminApproverPimpinan = in_array($userRole, ['admin', 'approver', 'pimpinan']);

        if (!$isAdminApproverPimpinan) {
            $this->toast('error', 'You are not authorized to approve documents.');
            return;
        }

        if (!$documentType->requires_approval) {
            $document->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            $document->approvals()->create([
                'approver_id' => auth()->id(),
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);

            $this->toast('success', 'Document successfully approved.');
            $this->approvalModal = false;
            return;
        }

        switch ($document->status) {
            case 'waiting_approval':
                if (in_array($userRole, ['admin', 'approver'])) {
                    $document->update([
                        'status' => 'waiting_first_approval',
                        'approved_by' => auth()->id(),
                    ]);

                    $document->approvals()->create([
                        'approver_id' => auth()->id(),
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                    ]);

                    $this->toast('success', 'Document moved to first approval stage.');
                } elseif (auth()->id() === $document->first_approver_id) {
                    $document->update([
                        'status' => 'waiting_final_approval',
                        'approved_by' => auth()->id(),
                    ]);

                    $document->approvals()->create([
                        'approver_id' => auth()->id(),
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                    ]);

                    $this->toast('success', 'Document moved to final approval stage.');
                } elseif (auth()->id() === $document->final_approver_id) {
                    $document->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);

                    $document->approvals()->create([
                        'approver_id' => auth()->id(),
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                    ]);

                    $this->toast('success', 'Document directly approved.');
                }
                break;

            case 'waiting_first_approval':
                if (auth()->id() === $document->first_approver_id || $userRole === 'pimpinan') {
                    $document->update([
                        'status' => 'waiting_final_approval',
                        'approved_by' => auth()->id(),
                    ]);

                    $document->approvals()->create([
                        'approver_id' => auth()->id(),
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                    ]);

                    $this->toast('success', 'Document moved to final approval stage.');
                }
                break;

            case 'waiting_final_approval':
                if (auth()->id() === $document->final_approver_id) {
                    $document->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);

                    $document->approvals()->create([
                        'approver_id' => auth()->id(),
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                    ]);

                    $this->toast('success', 'Document finally approved.');
                }
                break;

            default:
                $this->toast('error', 'Invalid document status for approval.');
        }

        $this->approvalModal = false;
    }

    public function rejectDocument(string $notes)
    {
        $document = Document::findOrFail($this->selectedDocumentId);

        $document->update([
            'status' => 'rejected',
            'approval_notes' => $notes,
            'approved_by' => auth()->id(),
        ]);

        $this->toast('error', 'Document rejected.');
        $this->approvalModal = false;
    }

    private function determineNextStatus(Document $document): string
    {
        if ($document->first_approver_id && !$document->final_approver_id) {
            return $document->first_approver_id === auth()->id() ? 'waiting_final_approval' : $document->status;
        }

        return 'approved';
    }

    private function canApprove(Document $document): bool
    {
        $userRole = auth()->user()->role;
        $userId = auth()->id();

        $isAdminApproverPimpinan = in_array($userRole, ['admin', 'approver', 'pimpinan']);

        $documentType = DocumentType::find($document->document_type_id);
        $requiresApproval = $documentType->requires_approval;

        if (!$requiresApproval && $isAdminApproverPimpinan) {
            return true;
        }

        if ($requiresApproval) {
            switch ($document->status) {
                case 'waiting_approval':
                    if (in_array($userRole, ['admin', 'approver'])) {
                        return true;
                    }
                    if ($userId === $document->final_approver_id) {
                        return true;
                    }
                    break;

                case 'waiting_first_approval':
                    return $userId === $document->first_approver_id || $userId === $document->final_approver_id;

                case 'waiting_final_approval':
                    return $userId === $document->final_approver_id;
            }
        }

        return false;
    }

    public function openApprovalModal(int $documentId)
    {
        $this->selectedDocumentId = $documentId;
        $this->approvalModal = true;
    }

    public function headers(): array
    {
        return [
            [
                'key' => 'title',
                'label' => 'Title',
                'sortable' => true,
            ],
            [
                'key' => 'document_number',
                'label' => 'Document Number',
                'sortable' => true,
            ],
            [
                'key' => 'document_date',
                'label' => 'Document Date',
                'sortable' => true,
            ],
            [
                'key' => 'created_by_name',
                'label' => 'Creator',
                'sortable' => true,
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'sortable' => true,
            ],
            [
                'key' => 'actions',
                'label' => 'Actions',
            ],
        ];
    }

    public function documents()
    {
        return Document::query()
            ->select('documents.*', 'users.name as created_by_name')
            ->leftJoin('users', 'documents.created_by', '=', 'users.id')
            ->when(
                $this->search,
                fn(Builder $q) => $q
                    ->where('documents.title', 'like', "%{$this->search}%")
                    ->orWhere('documents.document_number', 'like', "%{$this->search}%")
                    ->orWhere('users.name', 'like', "%{$this->search}%"),
            )
            ->when($this->selectedDepartmentId, fn(Builder $q) => $q->whereHas('creator.department', fn(Builder $q) => $q->where('id', $this->selectedDepartmentId)))
            ->when($this->selectedDocumentTypeId, fn(Builder $q) => $q->where('document_type_id', $this->selectedDocumentTypeId))
            ->where('status', $this->activeTab)
            ->when($this->dateRange, function (Builder $q) {
                if (isset($this->dateRange[0]) && isset($this->dateRange[1])) {
                    $q->whereBetween('document_date', $this->dateRange);
                }
            })
            ->when($this->sortBy['column'], fn(Builder $q) => $q->orderBy($this->sortBy['column'], $this->sortBy['direction']))
            ->paginate(10);
    }

    public function canUserApproveDocument($documentId): bool
    {
        $document = Document::find($documentId);
        return $document ? $this->canApprove($document) : false;
    }

    public function with(): array
    {
        return [
            'documents' => $this->documents(),
            'headers' => $this->headers(),
            'departments' => Department::all(),
            'documentTypes' => DocumentType::all(),
            'documentCounts' => $this->documentCounts,
            'canApprove' => $this->canUserApproveDocument($this->selectedDocumentId),
        ];
    }
};
?>

<div>
    <!-- Header -->
    <x-header title="Document Approval" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search documents..." wire:model.live.debounce="search" clearable
                icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" badge='{{ $this->activeFiltersCount }}' @click="$wire.drawer = true" responsive
                icon="o-funnel" />
        </x-slot:actions>
    </x-header>

    <!-- Status Tabs -->
    <x-tabs wire:model.live="activeTab">
        <x-tab name="waiting_approval">
            <x-slot:label>
                Waiting Approval
                <x-badge :value="$documentCounts['waiting_approval']" class="badge-warning" />
            </x-slot:label>
        </x-tab>
        <x-tab name="waiting_first_approval">
            <x-slot:label>
                First Approval
                <x-badge :value="$documentCounts['waiting_first_approval']" class="badge-info" />
            </x-slot:label>
        </x-tab>
        <x-tab name="waiting_final_approval">
            <x-slot:label>
                Final Approval
                <x-badge :value="$documentCounts['waiting_final_approval']" class="badge-info" />
            </x-slot:label>
        </x-tab>
        <x-tab name="approved">
            <x-slot:label>
                Approved
                <x-badge :value="$documentCounts['approved']" class="badge-success" />
            </x-slot:label>
        </x-tab>
        <x-tab name="signed">
            <x-slot:label>
                Signed
                <x-badge :value="$documentCounts['signed']" class="badge-success" />
            </x-slot:label>
        </x-tab>
        <x-tab name="rejected">
            <x-slot:label>
                Rejected
                <x-badge :value="$documentCounts['rejected']" class="badge-error" />
            </x-slot:label>
        </x-tab>
        <x-tab name="archived">
            <x-slot:label>
                Archived
                <x-badge :value="$documentCounts['archived']" class="badge-secondary" />
            </x-slot:label>
        </x-tab>
    </x-tabs>

    <!-- Table -->
    <x-card>
        <x-table :headers="$headers" :rows="$documents" :sort-by="$sortBy" with-pagination>
            @scope('cell_status', $document)
                <x-badge :value="ucfirst(str_replace('_', ' ', $document->status))" :color="match ($document->status) {
                    'waiting_approval' => 'warning',
                    'waiting_first_approval' => 'info',
                    'waiting_final_approval' => 'info',
                    'approved' => 'success',
                    'signed' => 'success',
                    'rejected' => 'error',
                    'archived' => 'secondary',
                    default => 'info',
                }" />
            @endscope

            @scope('actions', $document)
                <x-button label="View Details" class="btn-sm btn-secondary"
                    @click="$wire.openApprovalModal({{ $document->id }})" />
            @endscope
        </x-table>
    </x-card>

    <!-- Filter Drawer -->
    <x-drawer wire:model="drawer" title="Filters" right separator with-close-button class="lg:w-1/3">
        <div class="grid gap-5">
            <x-select placeholder="Select Department" wire:model.live="selectedDepartmentId" :options="$departments"
                clearable />
            <x-select placeholder="Select Document Type" wire:model.live="selectedDocumentTypeId" :options="$documentTypes"
                clearable />
            @php
                $config2 = ['mode' => 'range'];
            @endphp
            <x-datepicker wire:model.live="dateRangeString" placeholder="Filter by date" icon="o-calendar"
                :config="$config2" inline clearable />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>

    <!-- Approval Modal -->
    <x-modal wire:model="approvalModal" title="Document Details" separator>
        @php
            $document = $documents->firstWhere('id', $selectedDocumentId);
            $documentType = $document ? DocumentType::find($document->document_type_id) : null;
            $userRole = auth()->user()->role;
            $userId = auth()->id();
        @endphp

        @if ($document)
            <div class="grid gap-4">
                <p><strong>Title:</strong> {{ $document->title }}</p>
                <p><strong>Document Number:</strong> {{ $document->document_number }}</p>
                <p><strong>Date:</strong> {{ $document->document_date }}</p>
                <p><strong>Creator:</strong> {{ $document->created_by_name ?? 'Unknown' }}</p>
                <p><strong>Document Type:</strong> {{ $documentType->name ?? 'Unknown' }}</p>
                <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $document->status)) }}
                    @if (in_array($document->status, ['approved', 'rejected']))
                        by {{ $document->approver->name ?? 'Unknown' }}
                    @endif
                </p>
                <p><strong>Approval Required:</strong>
                    {{ $documentType && $documentType->requires_approval ? 'Yes' : 'No' }}
                </p>

                @if ($documentType && $documentType->requires_approval)
                    <div>
                        <strong>Approval Flow:</strong>
                        <ul>
                            @if ($document->first_approver_id)
                                <li>First Approver: {{ $document->first_approver->name ?? 'Not assigned' }}</li>
                            @endif
                            @if ($document->final_approver_id)
                                <li>Final Approver: {{ $document->final_approver->name ?? 'Not assigned' }}</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <p><strong>Approval History:</strong></p>
                <ul>
                    @foreach ($document->approvals as $approval)
                        <li>{{ $approval->approver->name }} -
                            {{ ucfirst($approval->approval_status) }}
                            ({{ $approval->approved_at->format('d-m-Y H:i') }})
                        </li>
                    @endforeach
                </ul>

                <iframe src="{{ asset($document->file_path) }}" class="w-full h-96"></iframe>
            </div>

            <x-slot:actions>
                @php
                    $isAdminApproverPimpinan = in_array($userRole, ['admin', 'approver', 'pimpinan']);
                    $requiresApproval = $documentType ? $documentType->requires_approval : false;
                    $canApprove = false;
                    
                    switch($document->status) {
                        case 'waiting_approval':
                            $canApprove = (!$requiresApproval && $isAdminApproverPimpinan) || 
                                        ($requiresApproval && (in_array($userRole, ['admin', 'approver']) || 
                                        $userId === $document->first_approver_id ||
                                        $userId === $document->final_approver_id));
                            break;
                        case 'waiting_first_approval':
                            $canApprove = $userId === $document->first_approver_id;
                            break;
                        case 'waiting_final_approval':
                            $canApprove = $userId === $document->final_approver_id;
                            break;
                    }
                @endphp

                @if (in_array($document->status, ['waiting_approval', 'waiting_first_approval', 'waiting_final_approval']))
                    @if ($canApprove)
                        <x-button label="Reject" class="btn-error" x-on:click="$dispatch('open-reject-modal')" />
                        <x-button label="Approve" class="btn-primary" wire:click="approveDocument" />
                    @else
                        <p class="text-warning">You are not authorized to approve this document at this stage.</p>
                    @endif
                @else
                    <p class="text-info">This document has already been {{ str_replace('_', ' ', $document->status) }}.</p>
                @endif
            </x-slot:actions>
        @else
            <p>Document not found.</p>
        @endif
    </x-modal>
</div>