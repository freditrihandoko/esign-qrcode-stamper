<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Models\Setting;
use Livewire\Attributes\Rule; 

new class extends Component {
    use Toast;

    public Setting $settings;

    #[Rule('required|min:3')]
    public string $website_name = '';

    #[Rule('required|email')]
    public string $email = '';

    public bool $show_document_preview = true;

    #[Rule('required|numeric|min:512|max:20580')]
    public int $max_document_size = 5120; //min 512kb max 20MB

    public function with(): array
    {
        return [
            'settings' => Setting::first(),
        ];
    }

    public function save(): void
    {
        // Validate the data
        $validated = $this->validate();

        // Update settings
        $this->settings->update([
            'website_name' => $this->website_name,
            'email' => $this->email,
            'show_document_preview' => $this->show_document_preview,
            'max_document_size' => $this->max_document_size,
        ]);

        // Display success message
        $this->success('Settings updated successfully.');
    }

    public function mount(): void
    {
        $this->settings = Setting::first();
        
        // Fill the form with existing settings
        $this->fill([
            'website_name' => $this->settings->website_name,
            'email' => $this->settings->email,
            'show_document_preview' => $this->settings->show_document_preview,
            'max_document_size' => $this->settings->max_document_size,
        ]);
    }
};
?>

<div>
    <x-header title="System Settings" separator />
    
    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-form wire:submit="save">
                <x-input 
                    label="Website Name" 
                    wire:model="website_name" 
                    placeholder="Enter website name" 
                />
                
                <x-input 
                    label="Email" 
                    wire:model="email" 
                    type="email"
                    placeholder="Enter email address" 
                />
                
                <x-checkbox 
                    label="Show Document Preview" 
                    wire:model="show_document_preview" 
                />
                
                <x-input 
                    label="Maximum Document Size (KB)" 
                    wire:model="max_document_size" 
                    type="number"
                    hint="1MB = 1024 KB" 
                />

                <x-slot:actions>
                    <x-button label="Cancel" link="/" />
                    <x-button 
                        label="Save Changes" 
                        icon="o-paper-airplane" 
                        spinner="save" 
                        type="submit" 
                        class="btn-primary" 
                    />
                </x-slot:actions>
            </x-form>
        </div>
       
    </div>
</div>