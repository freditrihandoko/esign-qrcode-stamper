<?php

use App\Models\Document;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public Document $document;
    public bool $canApprove = false;
    public bool $showApprovalModal = false;
    public string $approvalNotes = '';

    public function mount(Document $document)
    {
        $this->document = $document->load(['creator.department', 'documentType', 'first_approver', 'final_approver']);
        
        // Check if current user can approve this document
        $user = auth()->user();
        $this->canApprove = (
            $document->status === 'waiting_first_approval' || 'waiting_approval' && $user->id === $document->first_approver_id
        ) || (
            $document->status === 'waiting_final_approval' || 'waiting_approval' && $user->id === $document->final_approver_id
        );
    }

    public function downloadDocument()
    {
        if (!$this->document->file_path) {
            $this->error('Dokumen tidak tersedia untuk diunduh.');
            return;
        }

        return response()->download(storage_path('app/public/' . $this->document->file_path));
    }

    public function downloadSignedDocument()
    {
        if (!$this->document->signed_file_path) {
            $this->error('Dokumen yang ditandatangani tidak tersedia.');
            return;
        }

        return response()->download(storage_path('app/public/' . $this->document->signed_file_path));
    }

    public function approveDocument()
    {
        $this->validate([
            'approvalNotes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $isFirstApprover = $user->id === $this->document->first_approver_id;
            
            // Create approval record
            $this->document->approvals()->create([
                'approver_id' => $user->id,
                'approval_status' => 'approved',
                'notes' => $this->approvalNotes,
                'approved_at' => now(),
            ]);

            // Update document status
            if ($isFirstApprover) {
                $this->document->status = 'waiting_final_approval';
            } else {
                $this->document->status = 'approved';
                $this->document->approved_by = $user->id;
                $this->document->approved_at = now();
            }
            
            $this->document->save();
            
            DB::commit();
            $this->success('Dokumen berhasil disetujui.');
            $this->showApprovalModal = false;
            $this->redirect(route('documents.show', $this->document));
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Terjadi kesalahan saat menyetujui dokumen.');
        }
    }
};

?>

<div>
    <!-- Header with Actions -->
    <x-header :title="$document->title" separator>
        <x-slot:actions>
            <div class="flex space-x-2">
                @if($document->file_path)
                    <x-button 
                        label="Unduh Dokumen" 
                        icon="o-arrow-down-tray" 
                        wire:click="downloadDocument"
                        class="btn-warning"
                    />
                @endif

                @if($document->signed_file_path)
                    <x-button 
                        label="Unduh Dokumen Tertandatangani" 
                        icon="o-arrow-down-on-square" 
                        wire:click="downloadSignedDocument"
                        class="btn-success"
                    />
                @endif

                @if (in_array($document->status, ['waiting_approval', 'waiting_first_approval', 'waiting_final_approval']))
                    @if($canApprove)
                        <x-button 
                            label="Setujui Dokumen" 
                            icon="o-check" 
                            class="btn-primary"
                            wire:click="$toggle('showApprovalModal')"
                        />
                    @else
                        <x-alert title="You are not authorized to approve this document at this stage." icon="o-exclamation-triangle" class="alert-warning"  dismissible />
                        {{-- <p class="text-warning">You are not authorized to approve this document at this stage.</p> --}}
                    @endif
                   
                @else
                {{-- <x-alert title="This document has already been {{ str_replace('_', ' ', $document->status) }}." icon="o-exclamation-triangle" dismissible /> --}}
                    {{-- <p class="text-info">This document has already been {{ str_replace('_', ' ', $document->status) }}.</p> --}}
                @endif
            </div>
        </x-slot:actions>
    </x-header>

    <!-- Document Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Main Information -->
        <x-card>
            <h3 class="font-semibold text-lg mb-4">Informasi Dokumen</h3>
            
            <div class="space-y-4">
                <div>
                    <x-input 
                        label="Nomor Dokumen"
                        value="{{ $document->document_number  ?? ''}}"
                        readonly
                    />
                </div>

                <div>
                    <x-input 
                        label="Tanggal Dokumen"
                        value="{{ $document->document_date?->format('d/m/Y')  ?? ''}}"
                        readonly
                    />
                </div>

                <div>
                    <x-input 
                        label="Jenis Dokumen"
                        value="{{ $document->documentType->name ?? '' }}"
                        readonly
                    />
                </div>

                <div>
                    <x-textarea 
                        label="Deskripsi"
                        value="{{ $document->description ?? '' }}"
                        readonly
                    />
                </div>
            </div>
        </x-card>

        <!-- Status and Approval Information -->
        <x-card>
            <h3 class="font-semibold text-lg mb-4">Status & Persetujuan</h3>
            
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <span class="font-medium">Status:</span>
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
                </div>

                <div>
                    <x-input 
                        label="Dibuat Oleh"
                        value="{{ $document->creator->name }} ({{ $document->creator->department->name ?? '-' }})"
                        readonly
                    />
                </div>

                <div>
                    <x-input 
                        label="Penyetuju Pertama"
                        value="{{ $document->first_approver?->name ?? '-' }}"
                        readonly
                    />
                </div>

                <div>
                    <x-input 
                        label="Penyetuju Akhir"
                        value="{{ $document->final_approver?->name ?? '-' }}"
                        readonly
                    />
                </div>

                @if($document->approved_at)
                    <div>
                        <x-input 
                            label="Tanggal Disetujui"
                            value="{{ $document->approved_at?->format('d/m/Y H:i') }}"
                            readonly
                        />
                    </div>
                @endif
            </div>
        </x-card>
        
    </div>

    <!-- Approval History -->
    @if($document->approvals->isNotEmpty())
        <x-card class="mt-6">
            <h3 class="font-semibold text-lg mb-4">Riwayat Persetujuan</h3>
            
            <x-table :headers="[
                ['key' => 'date', 'label' => 'Tanggal'],
                ['key' => 'approver', 'label' => 'Penyetuju'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'notes', 'label' => 'Catatan']
            ]" :rows="$document->approvals">
                @scope('cell_date', $approval)
                    {{ $approval->created_at->format('d/m/Y H:i') }}
                @endscope

                @scope('cell_approver', $approval)
                    {{ $approval->approver->name }}
                @endscope

                @scope('cell_status', $approval)
                    <x-badge 
                        :value="ucfirst($approval->approval_status)"
                        :color="match ($approval->approval_status) {
                            'approved' => 'success',
                            'rejected' => 'error',
                            default => 'warning',
                        }"
                    />
                @endscope

                @scope('cell_notes', $approval)
                    {{ $approval->notes ?? '-' }}
                @endscope
            </x-table>
        </x-card>
    @endif

    <!-- Approval Modal -->
    <x-modal wire:model="showApprovalModal" title="Persetujuan Dokumen" separator>
        <div class="space-y-4">
            <x-textarea 
                wire:model="approvalNotes"
                label="Catatan Persetujuan"
                placeholder="Masukkan catatan persetujuan (opsional)"
            />
        </div>

        <x-slot:actions>
            <div class="flex justify-end space-x-2">
                <x-button 
                    label="Batal"
                    wire:click="$toggle('showApprovalModal')"
                />
                <x-button 
                    label="Setujui"
                    class="btn-primary"
                    wire:click="approveDocument"
                />
            </div>
        </x-slot:actions>
    </x-modal>
</div>