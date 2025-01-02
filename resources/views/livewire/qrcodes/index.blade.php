<?php
use App\Models\QrCode;
use App\Models\Document;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public string $selectedMonth = '';
    public string $selectedYear = '';
    public string $selectedVerificationStatus = '';
    public array $months = [];
    public array $years = [];
    public array $sortBy = ['column' => 'generated_at', 'direction' => 'desc'];

    public function mount()
    {
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

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'document_id', 'label' => 'Document', 'class' => 'w-64', 'sortable' => true],
            ['key' => 'document_number', 'label' => 'Doc Number', 'class' => 'w-64'],
            ['key' => 'unique_hash', 'label' => 'Unique Hash', 'class' => 'w-32'],
            ['key' => 'generated_at', 'label' => 'Generated At', 'sortable' => true],
            ['key' => 'is_verified', 'label' => 'Verified', 'class' => 'w-24', 'sortable' => true],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-24'],
        ];
    }

    public function getQrCodeQuery()
    {
        $query = QrCode::query()->with('document');

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('unique_hash', 'like', "%{$this->search}%")
                    ->orWhereHas('document', function ($docQuery) {
                        $docQuery->where('title', 'like', "%{$this->search}%");
                    });
            });
        }

        // Apply date filters
        if ($this->selectedMonth && $this->selectedYear) {
            $query->whereMonth('generated_at', $this->selectedMonth)
                  ->whereYear('generated_at', $this->selectedYear);
        }

        // Apply verification status filter
        if ($this->selectedVerificationStatus !== '') {
            $query->where('is_verified', $this->selectedVerificationStatus === 'verified');
        }

        return $query;
    }

    public function clearFilters()
    {
        $this->selectedMonth = date('n');
        $this->selectedYear = date('Y');
        $this->selectedVerificationStatus = '';
        $this->search = '';
    }

    public function deleteQrCode($id)
    {
        try {
            $qrCode = QrCode::findOrFail($id);
            if ($qrCode->qr_code_path && \Storage::exists($qrCode->qr_code_path)) {
                \Storage::delete($qrCode->qr_code_path);
            }
            $qrCode->delete();
            $this->success('QR Code deleted successfully.');
        } catch (\Exception $e) {
            $this->error('Error deleting QR Code: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'qrCodes' => $this->getQrCodeQuery()
                ->orderBy(...array_values($this->sortBy))
                ->paginate(10),
            'headers' => $this->headers(),
            'verificationStatuses' => [
                ['id' => 'verified', 'name' => 'Verified'],
                ['id' => 'unverified', 'name' => 'Unverified'],
            ],
        ];
    }
};
?>

<div>
    <x-header title="Document QR Codes" separator>
        <x-slot:actions>
            <x-input 
                placeholder="Search QR codes..." 
                wire:model.live.debounce="search" 
                clearable 
                icon="o-magnifying-glass" 
            />
            <x-button
                label="Generate New QR Code"
                wire:navigate
                href="{{ route('qrcodes.create') }}"
                icon="o-plus"
                class="btn-primary"
            />
        </x-slot:actions>
    </x-header>

    <!-- Filter Section -->
    <x-card class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-select 
                label="Month" 
                wire:model.live="selectedMonth" 
                :options="$months" 
                placeholder="Select Month" 
            />

            <x-select 
                label="Year" 
                wire:model.live="selectedYear" 
                :options="$years" 
                placeholder="Select Year" 
            />

            <x-select 
                label="Verification Status" 
                wire:model.live="selectedVerificationStatus" 
                :options="$verificationStatuses" 
                placeholder="Select Status" 
            />
        </div>

        <div class="mt-4 flex justify-end">
            <x-button 
                label="Reset Filters" 
                wire:click="clearFilters" 
                icon="o-x-mark" 
                class="btn-ghost" 
            />
        </div>
    </x-card>

    <!-- QR Codes Table -->
    <x-card>
        <x-table
            :headers="$headers"
            :rows="$qrCodes"
            :sort-by="$sortBy"
            with-pagination
        >
            @scope('cell_document_id', $qrCode)
                {{ $qrCode->document->title }}
            @endscope

            @scope('cell_document_number', $qrCode)
            {{ $qrCode->document->document_number }}
        @endscope

            @scope('cell_unique_hash', $qrCode)
                <code>{{ Str::limit($qrCode->unique_hash, 10) }}</code>
            @endscope

            @scope('cell_generated_at', $qrCode)
                {{ $qrCode->generated_at->format('Y-m-d H:i') }}
            @endscope

            @scope('cell_is_verified', $qrCode)
                <x-badge
                    :value="$qrCode->is_verified ? 'Verified' : 'Unverified'"
                    :color="$qrCode->is_verified ? 'success' : 'warning'"
                />
            @endscope

            @scope('actions', $qrCode)
                <div class="flex space-x-1">
                    <x-button
                        icon="o-eye"
                        wire:navigate
                        href="{{ route('qrcodes.show', $qrCode) }}"
                        class="btn-ghost btn-sm"
                        title="View QR Code"
                    />
                    <x-button
                        icon="o-trash"
                        wire:click="deleteQrCode({{ $qrCode->id }})"
                        wire:confirm="Are you sure you want to delete this QR Code?"
                        class="btn-ghost btn-sm text-red-500"
                        title="Delete QR Code"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>