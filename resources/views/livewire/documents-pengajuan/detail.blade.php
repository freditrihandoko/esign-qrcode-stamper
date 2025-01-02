<?php
use App\Models\Document;
use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    public Document $document;
    
    public $pdfFile = null;
    public bool $confirmReplace = false;
    public ?string $newFilePath = null;
    public ?Setting $settings;

    public function mount(Document $document)
    {
        $this->document = $document;
        $this->settings = Setting::first();
    }

    public function updatedPdfFile()
    {
        // Jika sudah ada dokumen sebelumnya, tampilkan konfirmasi
        if ($this->document->file_path) {
            $this->confirmReplace = true;
            $this->newFilePath = null;
        }
    }

    public function cancelReplace()
    {
        $this->confirmReplace = false;
        $this->pdfFile = null;
        $this->newFilePath = null;
    }

    public function uploadPdf($forceReplace = false)
    {
        // Cek apakah dokumen masih dalam status draft
        if ($this->document->status !== 'draft') {
            $this->error('Dokumen tidak dapat diubah setelah status berubah.');
            return;
        }

        // $this->validate([
        //     'pdfFile' => 'required|mimes:pdf|max:10240', // Max 10MB
        // ]);
        $this->validate([
            'pdfFile' => 'required|mimes:pdf|max:' . $this->settings->max_document_size ?? 10240,  // Ukuran dari settings
        ], [
            'pdfFile.max' => 'Ukuran file tidak boleh lebih dari ' . ($this->settings->max_document_size / 1024) . 'MB'
        ]);

        // Jika sudah ada dokumen sebelumnya dan belum dikonfirmasi
        if ($this->document->file_path && !$forceReplace && !$this->confirmReplace) {
            $this->confirmReplace = true;
            return;
        }

        // Hapus file lama jika ada
        if ($this->document->file_path) {
            Storage::disk('public')->delete($this->document->file_path);
        }

        // Simpan file baru
        $path = $this->pdfFile->store('documents/pdfs', 'public');

        $this->document->update([
            'file_path' => $path,
        ]);

        // Reset state
        $this->confirmReplace = false;
        $this->pdfFile = null;

        $this->success('Dokumen berhasil diunggah.');
    }

    public function removePdf()
    {
        if ($this->document->status !== 'draft') {
            $this->error('Dokumen tidak dapat diubah setelah status berubah.');
            return;
        }

        if ($this->document->file_path) {
            // Hapus file
            Storage::disk('public')->delete($this->document->file_path);

            $this->document->update([
                'file_path' => null,
            ]);

            $this->success('Dokumen berhasil dihapus.');
        }
    }

    public function submitForApproval()
    {
        // Logika submit untuk persetujuan
        if (!$this->document->file_path) {
            $this->error('Unggah dokumen PDF terlebih dahulu.');
            return;
        }

        $this->document->update([
            'status' => 'waiting_approval',
        ]);

        $this->success('Dokumen berhasil diajukan untuk persetujuan.');
    }

    public function getTimelineSteps()
    {
        $steps = [];

        // Document Creation Step
        $steps[] = [
            'title' => 'Draft Dokumen',
            'subtitle' => $this->document->created_at->format('d/m/Y H:i'),
            'status' => $this->document->status === 'draft' ? 'active' : 'completed'
        ];

        // PDF Upload Step
        $steps[] = [
            'title' => 'Unggah Dokumen PDF',
            'subtitle' => $this->document->file_path ? 
                $this->document->updated_at->format('d/m/Y H:i') : 
                null,
            'status' => $this->document->file_path ? 'completed' : 
                ($this->document->status === 'draft' ? 'pending' : 'completed'),
            'description' => $this->document->file_path ? 
                'Dokumen PDF telah diunggah' : 
                'Menunggu unggah dokumen PDF'
        ];

        // Approval Process Steps
        if ($this->document->documentType->requires_approval) {
            // First Approval Step
            $steps[] = [
                'title' => 'Persetujuan Tahap Pertama',
                'subtitle' => $this->document->first_approver ? 
                    $this->document->first_approver->name : 
                    'Belum ditentukan',
                'status' => match($this->document->status) {
                    'waiting_first_approval' => 'active',
                    'waiting_final_approval', 'approved', 'signed', 'archived' => 'completed',
                    default => 'pending'
                },
                'description' => $this->document->first_approver ? 
                    'Menunggu persetujuan ' . $this->document->first_approver->name : 
                    'Menunggu penunjukan approver pertama'
            ];

            // Final Approval Step
            $steps[] = [
                'title' => 'Persetujuan Akhir',
                'subtitle' => $this->document->final_approver ? 
                    $this->document->final_approver->name : 
                    'Belum ditentukan',
                'status' => match($this->document->status) {
                    'waiting_final_approval' => 'active',
                    'approved', 'signed', 'archived' => 'completed',
                    default => 'pending'
                },
                'description' => $this->document->final_approver ? 
                    'Menunggu persetujuan ' . $this->document->final_approver->name : 
                    'Menunggu penunjukan approver akhir'
            ];
        }

        // Final Status Steps
        $steps[] = [
            'title' => match($this->document->status) {
                'approved' => 'Dokumen Disetujui',
                'rejected' => 'Dokumen Ditolak',
                'signed' => 'Dokumen Ditandatangani',
                'archived' => 'Dokumen Diarsipkan',
                default => 'Menunggu Proses'
            },
            'subtitle' => $this->document->approved_at ? 
                $this->document->approved_at->format('d/m/Y H:i') : 
                null,
            'status' => in_array($this->document->status, ['approved', 'signed', 'archived']) ? 'completed' : 
                ($this->document->status === 'rejected' ? 'error' : 'pending'),
            'description' => $this->document->approval_notes ?? 
                match($this->document->status) {
                    'approved' => 'Dokumen telah disetujui',
                    'rejected' => 'Dokumen ditolak',
                    'signed' => 'Dokumen telah ditandatangani',
                    'archived' => 'Dokumen telah diarsipkan',
                    default => 'Menunggu proses selanjutnya'
                }
        ];

        return $steps;
    }
};
?>

<div>
    <x-header :title="$document->title" subtitle="{{ $document->document_number }}" separator>
        <x-slot:actions>
            <x-button label="Kembali" link="{{ route('documents-pengajuan.index') }}"
                icon="o-arrow-left" class="btn-ghost" />
            @if ($document->status == 'draft')
                <x-button label="Ajukan Persetujuan" wire:click="submitForApproval" class="btn-primary" spinner />
            @endif

        </x-slot:actions>
    </x-header>

    <div class="grid md:grid-cols-2 gap-4">
        <!-- Detail Dokumen -->
        <x-card title="Informasi Dokumen">
            <div class="space-y-3">
                <div>
                    <strong>Status:</strong>
                    <x-badge :value="ucfirst($document->status)" :color="match ($document->status) {
                        'draft' => 'gray',
                        'waiting_approval' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'error',
                        default => 'info',
                    }" />
                </div>
                <div><strong>Jenis Dokumen:</strong> {{ $document->documentType->name }}</div>
                <div><strong>Tanggal Dokumen:</strong> {{ $document->document_date }}</div>
            </div>

            <!-- Dalam x-card Informasi Dokumen -->
            @if ($document->documentType->requires_approval)
                @if ($document->first_approver)
                    <div>
                        <strong>First Approver:</strong>
                        {{ $document->first_approver->name }}
                        ({{ $document->first_approver->position }})
                    </div>
                @endif

                @if ($document->final_approver)
                    <div>
                        <strong>Final Approver:</strong>
                        {{ $document->final_approver->name }}
                        ({{ $document->final_approver->position }})
                    </div>
                @endif
            @endif
        </x-card>

        <!-- Upload PDF -->
        <x-card title="Dokumen PDF"
            subtitle="{{ $document->status == 'draft' ? 'Anda dapat mengunggah/mengganti dokumen' : 'Dokumen tidak dapat diubah' }}">
            @if ($document->status == 'draft')
                <div>
                    <x-file wire:model.live="pdfFile" accept=".pdf" label="Unggah Dokumen PDF" hint="Maksimal {{ $settings->max_document_size / 1024 }}MB"  />

                    @if ($document->file_path)
                        <div class="mt-4 flex items-center justify-start">
                            <div class="mr-5">
                                <span>...{{ substr(basename($document->file_path), -8) }}</span>
                                <a href="{{ Storage::url($document->file_path) }}" target="_blank"
                                    class="text-blue-500 hover:underline">
                                    Lihat File
                                </a>
                            </div>
                            <x-button label="Hapus" wire:click="removePdf" class="btn-outline btn-error btn-sm" />
                        </div>
                    @endif

                    @if ($confirmReplace)
                        <x-modal wire:model.live="confirmReplace" title="Konfirmasi Penggantian Dokumen" separator>
                            <div class="space-y-4">
                                <p>Anda yakin ingin mengganti dokumen yang sudah ada?</p>
                                <p class="text-sm text-gray-500">Dokumen PDF sebelumnya akan dihapus dan diganti dengan
                                    dokumen baru.</p>
                            </div>

                            <x-slot:actions>
                                <x-button label="Batal" @click="$wire.cancelReplace()" class="btn-outline" />
                                <x-button label="Ganti Dokumen" wire:click="uploadPdf(true)" class="btn-primary"
                                    spinner />
                            </x-slot:actions>
                        </x-modal>
                    @endif

                    @if ($pdfFile && !$confirmReplace)
                        <x-button label="Unggah" wire:click="uploadPdf" class="btn-primary mt-3" spinner />
                    @endif
                </div>
            @else
                <div>
                    @if ($document->file_path)
                        <iframe src="{{ Storage::url($document->file_path) }}" class="w-full h-96"></iframe>
                    @else
                        <p>Belum ada dokumen PDF</p>
                    @endif
                </div>
            @endif
        </x-card>

        <x-card title="Alur Dokumen" subtitle="Riwayat Proses Dokumen">
            <div>
                @php $steps = $this->getTimelineSteps(); @endphp
                @foreach($steps as $index => $step)
                    <x-timeline-item 
                        :title="$step['title']" 
                        :subtitle="$step['subtitle']"
                        :description="$step['description'] ?? null"
                        :first="$index === 0"
                        :last="$index === count($steps) - 1"
                        :pending="$step['status'] === 'pending'"
                        :error="$step['status'] === 'error'"
                        :active="$step['status'] === 'active'"
                    />
                @endforeach
            </div>
        </x-card>
    </div>
</div>
