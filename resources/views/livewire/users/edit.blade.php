<?php

use Livewire\Volt\Component;
use App\Models\User; 
use App\Models\Department;
use Mary\Traits\Toast;
use Livewire\WithFileUploads; 
use Livewire\Attributes\Rule; 
use App\Models\Country;
use App\Models\Language;

new class extends Component {

    use Toast, WithFileUploads;

    public User $user;

    // Required fields
    #[Rule('required')]
    public string $name = '';

    #[Rule('required|email')]
    public string $email = '';

    // Optional fields
    #[Rule('nullable')]
    public string $role = 'user';

    #[Rule('nullable|numeric')]
    public ?string $nip = null;

    #[Rule('nullable|string')]
    public ?string $position = null;

    #[Rule('nullable|exists:departments,id')]
    public ?int $department_id = null;

    #[Rule('nullable|image|max:1024')]
    public $signature_path;

    // Country and language selections
    #[Rule('nullable|exists:countries,id')]
    public ?int $country_id = null;

    #[Rule('required')]
    public array $my_languages = [];

    public function with(): array 
    {
        return [
            'countries' => Country::all(),
            'languages' => Language::all(),
            'departments' => Department::all(), // Available Departments
            'roles' => [
                ['id' => 'superadmin', 'name' => 'Superadmin'], 
                ['id' => 'admin', 'name' => 'Admin'], 
                ['id' => 'pimpinan', 'name' => 'Pimpinan'], 
                ['id' => 'approver', 'name' => 'Approver'], 
                ['id' => 'user', 'name' => 'User']
            ]
        ];
    }

    public function save(): void
    {
        // Validate the data
        $data = $this->validate();

        // Update user data
        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'nip' => $this->nip,
            'position' => $this->position,
            'department_id' => $this->department_id,
            'country_id' => $this->country_id,
        ]);

        // If a new signature is uploaded, store it and update the user's signature_path
        if ($this->signature_path) { 
            $url = $this->signature_path->store('signatures', 'public');
            $this->user->update(['signature_path' => "/storage/$url"]);
        }

        // Save user's languages (assuming a pivot table between users and languages)
        $this->user->languages()->sync($this->my_languages);

        // Display success message and redirect
        $this->success('User updated successfully.', redirectTo: '/users');
    }

    public function mount(): void
    {
        // Fill the form with existing user data
        $this->fill($this->user);
        $this->my_languages = $this->user->languages->pluck('id')->toArray();
    }
};
?>

<div>
    <x-header title="Edit User: {{ $user->name }}" separator /> 
    <div class="grid gap-5 lg:grid-cols-2"> 
        <div>
            <x-form wire:submit="save"> 
                <x-file label="Signature" wire:model="signature_path" accept="image/png, image/jpeg" crop-after-change> 
                    <img src="{{ $user->signature_path ?? '/placeholder-signature.jpg' }}" class="h-40 rounded-lg" />
                </x-file>
                <x-input label="Name" wire:model="name" />
                <x-input label="Email" wire:model="email" />
                <x-select label="Role" wire:model="role" :options="$roles" placeholder="---" />
                <x-input label="NIP" wire:model="nip" />
                <x-input label="Position" wire:model="position" />
                <x-select label="Department" wire:model="department_id" :options="$departments" placeholder="---" />
                <x-select label="Country" wire:model="country_id" :options="$countries" placeholder="---" />

                <x-choices-offline
                    label="Languages"
                    wire:model="my_languages"
                    :options="$languages"
                    searchable />

                <x-slot:actions>
                    <x-button label="Cancel" link="/users" />
                    <x-button label="Save" icon="o-paper-airplane" spinner="save" type="submit" class="btn-primary" />
                </x-slot:actions>
            </x-form>
        </div>  
        <div>
            <img src="/edit-form.png" width="300" class="mx-auto" />
        </div>
    </div>
</div>