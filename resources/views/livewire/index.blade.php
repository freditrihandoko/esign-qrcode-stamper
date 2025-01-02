<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Department;
use App\Models\User;

new class extends Component {
    public $totalDocuments;
    public $pendingApprovals;
    public $recentDocuments;
    public $totalUsers;
    public $totalDepartments;
    public $documentsByStatus;
    
    public function mount()
    {
        $user = Auth::user();
        
        // Common data for all roles
        $this->recentDocuments = Document::latest()
            ->when($user->role === 'user', function ($query) use ($user) {
                return $query->where('created_by', $user->id);
            })
            ->take(5)
            ->get();
            
        // Role specific data
        switch ($user->role) {
            case 'superadmin':
            case 'admin':
                $this->totalUsers = User::count();
                $this->totalDepartments = Department::count();
                $this->totalDocuments = Document::count();
                $this->pendingApprovals = Document::whereIn('status', [
                    'waiting_approval',
                    'waiting_first_approval',
                    'waiting_final_approval'
                ])->count();
                $this->documentsByStatus = Document::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->get();
                break;
                
            case 'pimpinan':
                // $this->totalDocuments = Document::count();
                // $this->pendingApprovals = Document::where('final_approver_id', $user->id)
                //     ->where('status', 'waiting_final_approval')
                //     ->count();
                // break;
                $this->totalDocuments = Document::where(function($query) use ($user) {
                    $query->where('first_approver_id', $user->id)
                        ->orWhere('final_approver_id', $user->id);
                })->count();
                $this->pendingApprovals = Document::where(function($query) use ($user) {
                    $query->where('first_approver_id', $user->id)
                        ->where('status', 'waiting_first_approval')
                        ->orWhere('status', 'waiting_approval');
                })->orWhere(function($query) use ($user) {
                    $query->where('final_approver_id', $user->id)
                        ->where('status', 'waiting_final_approval')
                        ->orWhere('status', 'waiting_approval');
                })->count();
                break;
                
            case 'approver':
                // $this->totalDocuments = Document::where(function($query) use ($user) {
                //     $query->where('first_approver_id', $user->id)
                //         ->orWhere('final_approver_id', $user->id);
                // })->count();
                // $this->pendingApprovals = Document::where(function($query) use ($user) {
                //     $query->where('first_approver_id', $user->id)
                //         ->where('status', 'waiting_first_approval')
                //         ->orWhere('status', 'waiting_approval');
                // })->orWhere(function($query) use ($user) {
                //     $query->where('final_approver_id', $user->id)
                //         ->where('status', 'waiting_final_approval')
                //         ->orWhere('status', 'waiting_approval');
                // })->count();
                // break;
                $this->totalDocuments = Document::where(function($query) use ($user) {
                    $query->where('first_approver_id', $user->id)
                        ->orWhere('final_approver_id', $user->id);
                })->count();
                $this->pendingApprovals = Document::whereIn('status', [
                    'waiting_approval',
                ])->count();

            default: // user
                $this->totalDocuments = Document::where('created_by', $user->id)->count();
                $this->pendingApprovals = Document::where('created_by', $user->id)
                    ->whereIn('status', [
                        'waiting_approval',
                        'waiting_first_approval',
                        'waiting_final_approval'
                    ])->count();
                break;
        }
    }
}; ?>

<div>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <p class="text-base-600">Welcome back, {{ Auth::user()->name }}</p>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Documents Card -->
            <div class="bg-base-100 rounded-lg shadow p-4">
                <h3 class="text-base-500 text-sm">Total Documents</h3>
                <p class="text-2xl font-bold">{{ $totalDocuments }}</p>
            </div>

            <!-- Pending Approvals Card -->
            <div class="bg-base-100 rounded-lg shadow p-4">
                <h3 class="text-base-500 text-sm">Pending Approvals</h3>
                <p class="text-2xl font-bold">{{ $pendingApprovals }}</p>
            </div>

            @if(in_array(Auth::user()->role, ['superadmin', 'admin']))
            <!-- Admin Only Stats -->
            <div class="bg-base-100 rounded-lg shadow p-4">
                <h3 class="text-base-500 text-sm">Total Users</h3>
                <p class="text-2xl font-bold">{{ $totalUsers }}</p>
            </div>

            <div class="bg-base-100 rounded-lg shadow p-4">
                <h3 class="text-base-500 text-sm">Total Departments</h3>
                <p class="text-2xl font-bold">{{ $totalDepartments }}</p>
            </div>
            @endif
        </div>

        <!-- Recent Documents -->
        <div class="bg-base rounded-lg shadow p-4 mb-6">
            <h2 class="text-lg font-semibold mb-4">Recent Documents</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Title</th>
                            <th class="px-4 py-2 text-left">Document Number</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDocuments as $document)
                        <tr>
                            <td class="px-4 py-2">{{ $document->title }}</td>
                            <td class="px-4 py-2">{{ $document->document_number }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded-full text-xs 
                                    @switch($document->status)
                                        @case('draft') bg-gray-100 text-gray-800 @break
                                        @case('waiting_approval') bg-yellow-100 text-yellow-800 @break
                                        @case('approved') bg-green-100 text-green-800 @break
                                        @case('rejected') bg-red-100 text-red-800 @break
                                        @default bg-blue-100 text-blue-800
                                    @endswitch">
                                    {{ str_replace('_', ' ', ucfirst($document->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ $document->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if(in_array(Auth::user()->role, ['superadmin', 'admin']))
        <!-- Document Status Overview (Admin Only) -->
        <div class="bg-base rounded-lg shadow p-4">
            <h2 class="text-lg font-semibold mb-4">Document Status Overview</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($documentsByStatus as $status)
                <div class="p-3 border rounded">
                    <h4 class="text-sm text-base-500">{{ str_replace('_', ' ', ucfirst($status->status)) }}</h4>
                    <p class="text-xl font-bold">{{ $status->count }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>