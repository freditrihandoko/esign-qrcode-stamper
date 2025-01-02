<?php

use App\Models\QrCode;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    public QrCode $qrCode;
    public bool $showVerificationModal = false;
    public bool $showRegenerateModal = false;
    public string $verificationCode = '';


    public function mount(QrCode $qrCode)
    {
        $this->qrCode = $qrCode;

    }

    public function confirmRegenerateQr()
    {
        $this->showRegenerateModal = true;
    }

    public function handlePlaceQr()
    {
        if ($this->qrCode->document->signed_file_path) {
            $this->confirmRegenerateQr();
        } else {
            $this->redirectToPlaceQr();
        }
    }

    public function redirectToPlaceQr()
    {
        return $this->redirect(route('pdfstamp.show', ['qrCode' => $this->qrCode->id]));
    }

    public function verifyQrCode()
    {
        // Implement QR code verification logic here
        if ($this->verificationCode === $this->qrCode->unique_hash) {
            $this->qrCode->update([
                'is_verified' => true,
                'verified_at' => now()
            ]);

            // Log verification
            $this->qrCode->verificationLogs()->create([
                'verified_by' => auth()->id(),
                'ip_address' => request()->ip(),
                'device_info' => request()->header('User-Agent'),
                'browser_info' => request()->header('User-Agent'),
                'is_successful' => true
            ]);

            $this->success('QR Code verified successfully');
            $this->showVerificationModal = false;
        } else {
            $this->error('Invalid verification code');
        }
    }
};
?>

<div class="container mx-auto px-4 py-8">
    @if(session()->has('qrCodePlaced'))
    <div role="alert" class="alert alert-success">
        <x-icon name="o-check" class="w-9 h-9" />
        <span>QR Code berhasil ditempatkan pada dokumen.</span>
      </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- QR Code Section -->
        <x-card title="QR Code Details">
            <div class="flex flex-col items-center space-y-4">
                <img src="{{ Storage::disk('public')->url($qrCode->qr_code_path) }}" 
                     alt="QR Code" 
                     class="w-64 h-64 object-contain" />
                
                <div class="w-full space-y-4">
                    <x-input 
                        label="Unique Hash" 
                        value="{{ $qrCode->unique_hash }}" 
                        readonly 
                        class="text-sm" 
                    />

                    @if($qrCode->verification_url)
                        <x-input 
                            label="Verification URL" 
                            value="{{ $qrCode->verification_url }}" 
                            readonly 
                            class="text-sm" 
                        />
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <x-input 
                            label="Generated At" 
                            value="{{ $qrCode->generated_at->format('Y-m-d H:i:s') }}" 
                            readonly 
                        />
                        <x-input 
                            label="Verification Status" 
                            value="{{ $qrCode->is_verified ? 'Verified' : 'Not Verified' }}" 
                            :class="$qrCode->is_verified ? 'text-green-600' : 'text-red-600'" 
                            readonly 
                        />
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Document Details Section -->
        <x-card title="Associated Document">
            <div class="space-y-4">
                <x-input 
                    label="Document Title" 
                    value="{{ $qrCode->document->title }}" 
                    readonly 
                />
                <x-input 
                    label="Document Number" 
                    value="{{ $qrCode->document->document_number }}" 
                    readonly 
                />
                <x-input 
                    label="Document Date" 
                    value="{{ $qrCode->document->document_date?->format('Y-m-d') }}" 
                    readonly 
                />
                <x-input 
                    label="Status" 
                    value="{{ ucwords(str_replace('_', ' ', $qrCode->document->status)) }}" 
                    readonly 
                />

                <!-- PDF Viewer/Download -->
               
                <div class="mt-4 flex space-x-2">
                    @if($qrCode->document->file_path)
                        <x-button 
                            label="View PDF" 
                            icon="o-document-text" 
                            class="btn-primary" 
                            onclick="window.open('{{ Storage::disk('public')->url($qrCode->document->file_path) }}', '_blank')" 
                        />
                        @endif
                    @if($qrCode->document->signed_file_path)
                        <x-button 
                        label="View Signed PDF" 
                        icon="o-document-text" 
                        class="btn-secondary" 
                        onclick="window.open('{{ Storage::disk('public')->url($qrCode->document->signed_file_path) }}', '_blank')" 
                    />
                    @endif
                   
                </div>
                
            </div>
        </x-card>

        <!-- Additional Metadata Section -->
        @if($qrCode->additional_metadata)
            <x-card title="Additional Metadata" class="md:col-span-2">
                <div class="grid grid-cols-2 gap-4">
                    @foreach(json_decode($qrCode->additional_metadata, true) as $metadata)
                        @if(!empty($metadata['key']) && !empty($metadata['value']))
                            <x-input 
                                label="{{ $metadata['key'] }}" 
                                value="{{ $metadata['value'] }}" 
                                readonly 
                            />
                        @endif
                    @endforeach
                </div>
            </x-card>
        @endif

        <!-- Verification Actions -->
        <div class="md:col-span-2 flex justify-center space-x-4">
            @if(!$qrCode->is_verified)
                <x-button 
                    label="Verify QR Code" 
                    class="btn-success" 
                    wire:click="$toggle('showVerificationModal')" 
                />
            @endif
            
            <x-button 
            label="Place QR Code" 
            class="btn-primary" 
            wire:click="handlePlaceQr"
        />
    

            <x-button 
                label="Back to List" 
                class="btn-outline" 
                link="{{ route('qrcodes.index') }}" 
            />
        </div>
    </div>

    <!-- Verification Modal -->
    @if($showVerificationModal)
        <x-modal wire:model="showVerificationModal" title="Verify QR Code">
            <div class="space-y-4">
                <x-input 
                    label="Enter Verification Code" 
                    wire:model="verificationCode" 
                    placeholder="Enter the unique hash to verify" 
                />
                <x-button 
                    label="Verify" 
                    wire:click="verifyQrCode" 
                    class="btn-primary" 
                />
            </div>
        </x-modal>
    @endif

    <!-- Regenerate Confirmation Modal -->
    @if($showRegenerateModal)
        <x-modal wire:model="showRegenerateModal" title="Regenerate QR Code">
            <div class="space-y-4">
                <p class="text-gray-600">
                    This document already has a signed file with a QR code. Do you want to regenerate the QR code placement?
                </p>
                <div class="flex justify-end space-x-2">
                    <x-button 
                        label="Cancel" 
                        class="btn-outline" 
                        wire:click="$toggle('showRegenerateModal')" 
                    />
                    <x-button 
                        label="Regenerate" 
                        class="btn-primary" 
                        wire:click="redirectToPlaceQr" 
                    />
                </div>
            </div>
        </x-modal>
    @endif
</div>