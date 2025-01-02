<?php

use App\Models\Document;
use App\Models\QrCode;
use App\Models\QrCodeGeneration;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QRCodeGenerator;

new class extends Component {
    use Toast;

    public ?int $document_id = null;
    public ?string $qr_generation_code = null;
    public ?string $verification_url = null;
    public array $additional_metadata = [];
    public ?string $generated_qr_code_path = null;
    public ?QrCode $generatedQrCode = null;

    public function mount()
    {
        if ($this->document_id) {
            $existingQrCode = QrCode::where('document_id', $this->document_id)->first();
            if ($existingQrCode) {
                return redirect()->route('qrcodes.show', $existingQrCode->id);
            }
        }

        $this->additional_metadata = [['key' => '', 'value' => '']];
    }

    public function getDocumentsProperty()
    {
        return Document::where('status', 'approved')->whereDoesntHave('qrCodes')->get();
    }

    public function getQrCodeGenerationsProperty()
    {
        return QrCodeGeneration::whereDoesntHave('qrCodes')->get();
    }

    public function addMetadata()
    {
        $this->additional_metadata[] = ['key' => '', 'value' => ''];
    }

    public function removeMetadata($index)
    {
        unset($this->additional_metadata[$index]);
        $this->additional_metadata = array_values($this->additional_metadata);
    }

    public function generateQrCode()
    {
        $this->validate([
            'document_id' => 'required|exists:documents,id',
            'verification_url' => 'nullable|url',
        ]);

        try {
            // Generate unique hash
            $uniqueHash = Str::random(40);

            // Create the verification URL if not provided
            $verificationUrl = $this->verification_url ?? config('app.url') . '/signed/' . $uniqueHash;

            // Generate QR Code image with the full verification URL
            $qrCodePath = $this->createQrCodeImage($verificationUrl);

            // Find QR Code Generation if code is provided
            $qrCodeGeneration = $this->qr_generation_code ? 
                QrCodeGeneration::where('qr_generation_code', $this->qr_generation_code)->first() : null;

            // Create QR Code record
            $this->generatedQrCode = QrCode::create([
                'document_id' => $this->document_id,
                'qr_generation_id' => $qrCodeGeneration?->id,
                'qr_code_path' => $qrCodePath,
                'unique_hash' => $uniqueHash,
                'verification_url' => $verificationUrl, // Save the full verification URL
                'additional_metadata' => count($this->additional_metadata) > 0 ? 
                    json_encode($this->additional_metadata) : null,
                'generated_at' => now(),
                'is_verified' => false,
            ]);

            return redirect()->route('qrcodes.show', $this->generatedQrCode->id);

        } catch (\Exception $e) {
            $this->error('QR Code generation failed: ' . $e->getMessage());
        }
    }

    private function createQrCodeImage(string $url): string
    {
        $directory = 'qrcodes';
        $filename = $directory . '/' . Str::random(40) . '.png';

        // Ensure directory exists
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Generate QR Code with the full URL
        $qrCodeData = QRCodeGenerator::format('png')
            ->size(300)
            ->generate($url);

        // Store QR Code data as a file
        Storage::disk('public')->put($filename, $qrCodeData);

        return $filename;
    }
};
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @if ($this->documents->count() > 0)
        <x-card title="Generate QR Code">
            <div class="grid gap-4">
                {{-- <x-select label="Select Document" wire:model="document_id" :options="$this->documents" option-label="title"
                    option-value="id" placeholder="Choose a document" required />
                 --}}
                 <x-choices-offline
                    label="Select Document"
                    wire:model="document_id"
                    :options="$this->documents"
                    placeholder="Choose a document"
                    option-label="title"
                    option-value="id"
                    single
                    searchable />

                <x-select label="QR Code Generation" wire:model="qr_generation_code" :options="$this->qrCodeGenerations"
                    option-label="qr_generation_code" option-value="qr_generation_code"
                    placeholder="Select QR Code Generation" hint="if it is empty, please generate it from Qrcode Generator" />

                <x-input label="Verification URL" wire:model="verification_url"
                    placeholder="Optional: Custom verification URL" 
                    hint="If not provided, will use default: {{ config('app.url') }}/signed/{hash}" />

                <div class="border-t my-4 pt-2">
                    <h3 class="text-lg font-semibold mb-2">Additional Metadata</h3>
                    @foreach ($additional_metadata as $index => $metadata)
                        <div class="flex gap-2 mb-2">
                            <x-input label="Key" wire:model="additional_metadata.{{ $index }}.key"
                                placeholder="Metadata Key" class="flex-1" />
                            <x-input label="Value" wire:model="additional_metadata.{{ $index }}.value"
                                placeholder="Metadata Value" class="flex-1" />
                            <x-button icon="o-trash" wire:click="removeMetadata({{ $index }})"
                                class="self-end text-red-500" />
                        </div>
                    @endforeach
                    <x-button label="Add Metadata" wire:click="addMetadata" class="btn-outline btn-sm" />
                </div>

                <x-button label="Generate QR Code" wire:click="generateQrCode" class="btn-primary mt-4" spinner />
            </div>
        </x-card>

        <x-card title="Preview">
            <p class="text-center text-gray-500">
                Select a document to generate its QR Code
            </p>
        </x-card>
    @else
        <x-card title="No Available Documents" class="col-span-full">
            <p class="text-center text-gray-500">
                There are no approved documents without QR codes available for generation.
            </p>
        </x-card>
    @endif
</div>