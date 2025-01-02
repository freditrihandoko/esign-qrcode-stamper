<?php

use Livewire\Volt\Component;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Livewire\Attributes\Rule;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use Toast, WithFileUploads;

    public User $user;

    // Required fields
    #[Rule('required')]
    public string $name = '';

    #[Rule('required|email')]
    public string $email = '';

    // Optional fields
    #[Rule('nullable|numeric')]
    public ?string $nip = null;

    // Position and department are read-only
    public ?string $position = null;
    public ?string $department_name = null;

    #[Rule('nullable|image|max:1024')]
    public $signature_path;

    // Country and language selections
    #[Rule('nullable|exists:countries,id')]
    public ?int $country_id = null;

    #[Rule('required')]
    public array $my_languages = [];

    // Password change fields
    #[Rule('nullable|min:8')]
    public ?string $new_password = null;

    #[Rule('nullable|same:new_password')]
    public ?string $password_confirmation = null;

    #[Rule('nullable|current_password')]
    public ?string $current_password = null;

    public function with(): array
    {
        return [
            'countries' => Country::all(),
            'languages' => Language::all(),
        ];
    }

    public function save(): void
    {
        // Validate the data
        $validated = $this->validate();

        // Password change logic
        if ($this->new_password) {
            $this->validate([
                'current_password' => 'required|current_password',
                'new_password' => 'required|min:8',
                'password_confirmation' => 'required|same:new_password',
            ]);

            $this->user->update([
                'password' => Hash::make($this->new_password)
            ]);
        }

        // Update user data
        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip,
            'country_id' => $this->country_id,
        ]);

        // If a new signature is uploaded, store it and update the user's signature_path
        if ($this->signature_path) {
            $url = $this->signature_path->store('signatures', 'public');
            $this->user->update(['signature_path' => "/storage/$url"]);
        }

        // Save user's languages
        $this->user->languages()->sync($this->my_languages);

        // Display success message and redirect
        $this->success('Profile updated successfully.');
    }

    public function mount(): void
    {
        // Ensure user can only edit their own profile
        $this->user = Auth::user();
        
        // Fill the form with existing user data
        $this->fill([
            'name' => $this->user->name,
            'email' => $this->user->email,
            'nip' => $this->user->nip,
            'position' => $this->user->position,
            'department_name' => $this->user->department->name ?? 'N/A',
            'country_id' => $this->user->country_id,
        ]);
        
        $this->my_languages = $this->user->languages->pluck('id')->toArray();
    }
};
?>

<div>
    <x-header title="Edit Profile" separator />
    <div class="grid gap-5 lg:grid-cols-2">
        <div>
            <x-form wire:submit="save">
                <x-file label="Signature" wire:model="signature_path" accept="image/png, image/jpeg" crop-after-change>
                    <img src="{{ $user->signature_path ?? '/placeholder-signature.jpg' }}" class="h-40 rounded-lg" />
                </x-file>
                
                <x-input label="Name" wire:model="name" />
                <x-input label="Email" wire:model="email" />
                <x-input label="NIP" wire:model="nip" />
                
                <!-- Read-only fields -->
                <x-input label="Position" wire:model="position" readonly />
                <x-input label="Department" wire:model="department_name" readonly />
                
                <x-select label="Country" wire:model="country_id" :options="$countries" placeholder="---" />

                <x-choices-offline
                    label="Languages"
                    wire:model="my_languages"
                    :options="$languages"
                    searchable />

                <!-- Password change section -->
                <x-card title="Change Password" class="mt-4">
                    <x-input label="Current Password" wire:model="current_password" type="password" />
                    <x-input label="New Password" wire:model="new_password" type="password" />
                    <x-input label="Confirm New Password" wire:model="password_confirmation" type="password" />
                </x-card>

                <x-slot:actions>
                    <x-button label="Cancel" link="/" />
                    <x-button label="Save Changes" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
                </x-slot:actions>
            </x-form>
        </div>
        <div>
            <img src="/edit-form.png" width="300" class="mx-auto" />
        </div>
    </div>
</div>