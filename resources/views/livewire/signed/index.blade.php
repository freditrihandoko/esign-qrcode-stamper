<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\QrCode;
use App\Models\Setting;
use App\Models\VerificationLog;
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new #[Layout('components.layouts.public')]
class extends Component {
    public $uniqueHash = '';
    public $qrCode = null;
    public $document = null;
    public $isVerified = false;
    public $verificationStatus = '';
    public $documentDetails = [];
    public $statusType = ''; 
    public $settings;

    public function mount($uniqueHash = null) 
    {
        $this->settings = Setting::first();
        
        if ($uniqueHash) {
            $this->uniqueHash = $uniqueHash;
            $this->verifyDocument();
        }
    }

    public function verifyDocument() 
    {
        try {
            if (empty($this->uniqueHash)) {
                $this->setStatus('Please enter a document hash code.', 'warning');
                return;
            }

            DB::beginTransaction();

            $this->qrCode = QrCode::where('unique_hash', $this->uniqueHash)
                ->with(['document' => function ($query) {
                    $query->with(['documentType', 'creator', 'approver']);
                }])
                ->first();

            if (!$this->qrCode) {
                $this->setStatus('Document not found or invalid QR code.', 'error');
                DB::rollBack();
                return;
            }

            if (!$this->qrCode->document) {
                $this->setStatus('Associated document not found.', 'error');
                DB::rollBack();
                return;
            }

            $this->document = $this->qrCode->document;
            $this->isVerified = true;
            
            // Prepare document details
            $this->documentDetails = [
                'Nomor Dokumen' => $this->document->document_number ?? 'N/A',
                'Nama Dokumen' => $this->document->title ?? 'N/A',
                'Jenis Dokumen' => $this->document->documentType->name ?? 'N/A',
                'Dibuat Oleh' => $this->document->creator->name ?? 'N/A',
                'Department' => $this->document->creator->department->name ?? 'N/A',
                'Tanggal Dokumen' => $this->document->document_date ? date('d F Y', strtotime($this->document->document_date)) : 'N/A',
                'Status' => ucfirst($this->document->status ?? 'N/A'),
                'Pengetuju Pertama' => $this->document->first_approver?->name ?? '-',
                'Pengetuju Akhir' => $this->document->final_approver?->name ?? '-',
                'Tanggal Disetujui' => $this->document->approved_at ? date('d F Y', strtotime($this->document->approved_at)) : 'Not approved yet',
            ];

            // Log verification
            VerificationLog::create([
                'qr_code_id' => $this->qrCode->id,
                'ip_address' => request()->ip(),
                'device_info' => request()->userAgent(),
                'browser_info' => request()->header('User-Agent'),
                'is_successful' => true
            ]);

            DB::commit();
            
            $this->setStatus('Document verified successfully.', 'success');
            
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            $this->setStatus('An error occurred during verification. Please try again later.', 'error');
        }
    }

    private function setStatus($message, $type) 
    {
        $this->verificationStatus = $message;
        $this->statusType = $type;
    }

    public function clearVerification()
    {
        $this->reset(['uniqueHash', 'qrCode', 'document', 'isVerified', 'verificationStatus', 'documentDetails', 'statusType']);
    }
}; ?>

<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-center mb-6">Document Verification</h1>

            @if(!$uniqueHash || $verificationStatus)
                <div class="mb-6">
                    <x-input 
                        wire:model.live.debounce.500ms="uniqueHash" 
                        label="Enter Document Hash"
                        placeholder="Enter the document hash code"
                        class="w-full"
                    />
                    <div class="flex-inline gap-2 mt-4">
                        <x-button 
                            wire:click="verifyDocument" 
                            class="w-full btn-primary"
                        >
                            Verify Document
                        </x-button>
                        @if($verificationStatus)
                            <x-button 
                                wire:click="clearVerification" 
                                class="w-full mt-4 btn-error btn-sm"
                            >
                                Clear
                            </x-button>
                        @endif
                    </div>
                </div>
            @endif

            @if($verificationStatus)
                <div class="mb-6">
                    <x-alert
                        title="{{ $verificationStatus }}"
                        class="alert-{{ $statusType }}"
                        dismissible
                    />
                </div>
            @endif

            @if($isVerified && $document)
                <div class="border rounded-lg p-4">
                    <h2 class="text-xl font-semibold mb-4">Document Details</h2>
                    
                    @foreach($documentDetails as $label => $value)
                        <div class="grid grid-cols-3 gap-4 mb-2 py-2 border-b last:border-b-0">
                            <div class="font-medium">{{ $label }}</div>
                            <div class="col-span-2">{{ $value }}</div>
                        </div>
                    @endforeach

                    @if($document->signed_file_path && $settings->show_document_preview)
                        <div class="mt-6">
                            <x-button 
                                label="View Document" 
                                link="{{ Storage::url($document->signed_file_path) }}" 
                                external 
                                icon="o-link" 
                                tooltip="View Document!" 
                            />
                        </div>
                    @elseif($document->signed_file_path && !$settings->show_document_preview)
                        <div class="mt-6">
                            <x-alert
                                title="Document preview is currently disabled by administrator"
                                class="alert-warning"
                            />
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>