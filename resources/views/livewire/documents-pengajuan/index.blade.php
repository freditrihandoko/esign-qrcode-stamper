<?php
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    #[Url]
    public string $selectedTab = 'draft';

    public string $search = '';
    public bool $createModal = false;
    public string $previewNumber = '';

    public string $title = '';
    public string $document_number = '';
    public int $document_type_id = 0;
    public ?string $description = null;
    public string $document_date = '';
    public bool $auto_document_number = true;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];
    public User $user;

    // Add count property for document status
    public function getStatusCountsProperty()
    {
        return [
            'draft' => $this->getDocumentCount('draft'),
            'process' => $this->getDocumentCount(['waiting_approval', 'waiting_first_approval', 'waiting_final_approval']),
            'approved' => $this->getDocumentCount(['approved', 'signed', 'archived']),
            'rejected' => $this->getDocumentCount('rejected'),
        ];
    }

    private function getDocumentCount($status): int
    {
        $query = $this->user->createdDocuments();

        if (is_array($status)) {
            return $query->whereIn('status', $status)->count();
        }

        return $query->where('status', $status)->count();
    }

    public function mount()
    {
        $this->user = auth()->user();
    }

    // Convert number to roman numeral
    private function numberToRoman($number): string
    {
        $map = [
            'XII' => 12,
            'XI' => 11,
            'X' => 10,
            'IX' => 9,
            'VIII' => 8,
            'VII' => 7,
            'VI' => 6,
            'V' => 5,
            'IV' => 4,
            'III' => 3,
            'II' => 2,
            'I' => 1,
        ];

        foreach ($map as $roman => $int) {
            if ($number == $int) {
                return $roman;
            }
        }
        return '';
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'document_number' => $this->auto_document_number ? 'nullable' : 'required|unique:documents,document_number',
            'document_date' => 'required|date',
            'document_type_id' => 'required|exists:document_types,id',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function generateDocumentNumber(): string
    {
        if (!$this->document_type_id || !$this->document_date) {
            return '';
        }

        $documentType = DocumentType::findOrFail($this->document_type_id);
        $documentDate = \Carbon\Carbon::parse($this->document_date);

        // Get last sequence number for this month and year
        $lastDocument = Document::whereYear('document_date', $documentDate->year)
            ->whereMonth('document_date', $documentDate->month)
            ->max('document_number');

        // Extract sequence number or start from 1
        $sequence = $lastDocument ? intval(explode('/', $lastDocument)[0]) + 1 : 1;

        // Convert month to roman numeral
        $romanMonth = $this->numberToRoman($documentDate->month);

        // Format: 137/SI/XII/2024
        return sprintf('%03d/%s/%s/%d', $sequence, $documentType->code, $romanMonth, $documentDate->year);
    }

    public function updatePreview()
    {
        if ($this->auto_document_number) {
            $this->previewNumber = $this->generateDocumentNumber();
        }
    }

    // Update these methods to trigger preview update
    public function updatedDocumentTypeId()
    {
        $this->updatePreview();
    }

    public function updatedDocumentDate()
    {
        $this->updatePreview();
    }

    public function updatedAutoDocumentNumber($value)
    {
        if ($value) {
            $this->document_number = '';
            $this->updatePreview();
        }
    }

    public function createDocument()
    {
        // Jika auto generate, generate nomor dokumen
        if ($this->auto_document_number) {
            $this->document_number = $this->generateDocumentNumber();
        }

        $this->validate();

        // Ambil document type untuk cek approval dan default approver
        $documentType = DocumentType::findOrFail($this->document_type_id);

        // Siapkan data dokumen
        $documentData = [
            'title' => $this->title,
            'document_number' => $this->document_number,
            'document_date' => $this->document_date,
            'document_type_id' => $this->document_type_id,
            'description' => $this->description,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ];

        // Jika requires_approval aktif, tambahkan approver
        if ($documentType->requires_approval) {
            // Tambahkan first approver jika tersedia
            if ($documentType->default_first_approver_id) {
                $documentData['first_approver_id'] = $documentType->default_first_approver_id;

                // Ubah status menjadi waiting_approval jika first approver ditentukan
                // $documentData['status'] = 'waiting_approval';
            }

            // Tambahkan final approver jika tersedia
            if ($documentType->default_final_approver_id) {
                $documentData['final_approver_id'] = $documentType->default_final_approver_id;
            }
        }

        $document = Document::create($documentData);

        // Jika ada first approver, kirim notifikasi
        if (isset($documentData['first_approver_id'])) {
            $firstApprover = User::find($documentData['first_approver_id']);
            // Kirim notifikasi ke first approver //nnti buatkan
            // $firstApprover->notify(new DocumentApprovalRequest($document));
        }

        $this->redirect(route('documents-pengajuan.detail', $document->id));
    }

    public function headers(): array
    {
        return [['key' => 'id', 'label' => '#', 'class' => 'w-1'], ['key' => 'title', 'label' => 'Judul', 'sortable' => true], ['key' => 'document_number', 'label' => 'Nomor Dokumen'], ['key' => 'status', 'label' => 'Status'], ['key' => 'actions', 'label' => 'Aksi']];
    }

    public function documents()
    {
        $statusGroups = [
            'draft' => ['draft'],
            'process' => ['waiting_approval', 'waiting_first_approval', 'waiting_final_approval'],
            'approved' => ['approved', 'signed', 'archived'],
            'rejected' => ['rejected'],
        ];

        return $this->user
            ->createdDocuments()
            ->when($this->search, fn(Builder $q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('document_number', 'like', "%{$this->search}%"))
            ->when($this->selectedTab, fn(Builder $q) => $q->whereIn('status', $statusGroups[$this->selectedTab]))
            ->orderBy(...array_values($this->sortBy))
            ->paginate(10);
    }

    public function with(): array
    {
        return [
            'documents' => $this->documents(),
            'headers' => $this->headers(),
            'documentTypes' => DocumentType::all(),
        ];
    }
};
?>

<div>
    <!-- Header -->
    <x-header title="Pengajuan Dokumen" separator>
        <x-slot:middle>
            <x-input placeholder="Cari Dokumen..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Buat Dokumen" @click="$wire.createModal = true" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <!-- Tabs -->
    <x-tabs wire:model.live="selectedTab" class="mb-4">
        <x-tab name="draft">
            <x-slot:label>
                Draft
                <x-badge :value="$this->statusCounts['draft']" class="badge-gray" />
            </x-slot:label>
        </x-tab>
        <x-tab name="process">
            <x-slot:label>
                Proses
                <x-badge :value="$this->statusCounts['process']" class="badge-warning" />
            </x-slot:label>
        </x-tab>
        <x-tab name="approved">
            <x-slot:label>
                Disetujui
                <x-badge :value="$this->statusCounts['approved']" class="badge-success" />
            </x-slot:label>
        </x-tab>
        <x-tab name="rejected">
            <x-slot:label>
                Ditolak
                <x-badge :value="$this->statusCounts['rejected']" class="badge-error" />
            </x-slot:label>
        </x-tab>
    </x-tabs>

    <!-- Tabel Dokumen -->
    <x-card>
        <x-table :headers="$headers" :rows="$documents" :sort-by="$sortBy" with-pagination>
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
                <x-button icon="o-eye" link="{{ route('documents-pengajuan.detail', $document->id) }}"
                    class="btn-ghost btn-sm" />
            @endscope
        </x-table>
    </x-card>

    <!-- Modal Buat Dokumen -->
    <x-modal wire:model="createModal" title="Buat Dokumen Baru" separator>
        <div class="grid gap-4">
            <x-input label="Judul Dokumen" wire:model.live="title" placeholder="Masukkan judul dokumen" />

            <div class="flex items-center space-x-4">
                <x-toggle label="Generate Nomor Dokumen Otomatis" wire:model.live="auto_document_number" />
            </div>

            @if ($auto_document_number)
                <div class="text-sm">
                    <span class="font-medium">Preview Nomor Dokumen:</span>
                    <span class="ml-2">{{ $previewNumber ?: 'Pilih jenis dokumen dan tanggal' }}</span>
                </div>
            @else
                <x-input label="Nomor Dokumen" wire:model.live="document_number"
                    placeholder="Masukkan nomor dokumen manual" />
            @endif

            <x-datepicker label="Tanggal Dokumen" wire:model.live="document_date" />

            <x-select label="Jenis Dokumen" :options="$documentTypes" option-value="id" option-label="name"
                wire:model.live="document_type_id" placeholder="Pilih Jenis Dokumen" />

            <x-textarea label="Deskripsi Dokumen" wire:model.live="description"
                placeholder="Masukkan deskripsi dokumen (opsional)" />
        </div>

        <x-slot:actions>
            <x-button label="Batal" @click="$wire.createModal = false" />
            <x-button label="Buat Dokumen" wire:click="createDocument" class="btn-primary" spinner />
        </x-slot:actions>
    </x-modal>
</div>
