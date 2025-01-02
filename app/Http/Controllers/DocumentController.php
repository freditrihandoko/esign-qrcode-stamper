<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // public function download(Document $document)
    // {
    //     // Check if user has permission to download
    //     if (!auth()->user()->can('view', $document)) {
    //         abort(403);
    //     }

    //     if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
    //         abort(404);
    //     }

    //     return Storage::disk('public')->download($document->file_path);
    // }

    // public function downloadSigned(Document $document)
    // {
    //     // Check if user has permission to download
    //     if (!auth()->user()->can('view', $document)) {
    //         abort(403);
    //     }

    //     if (!$document->signed_file_path || !Storage::disk('public')->exists($document->signed_file_path)) {
    //         abort(404);
    //     }

    //     return Storage::disk('public')->download($document->signed_file_path);
    // }
    private function checkAccess(Document $document)
    {
        $user = Auth::user(); // Dapatkan user yang sedang login

        switch ($user->role) {
            case 'superadmin':
            case 'admin':
                // Can see all documents
                return true;
            case 'pimpinan':
                // Can see documents from their department
                if ($document->creator->department_id !== $user->department_id) {
                    abort(403, 'Anda tidak memiliki izin untuk mengunduh dokumen ini.');
                }
                break;
            default:
                // Regular users can only see their own documents
                if ($document->created_by !== $user->id) {
                    abort(403, 'Anda tidak memiliki izin untuk mengunduh dokumen ini.');
                }
        }

        return true;
    }

    private function downloadFile(?string $filePath)
    {
        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        return Storage::disk('public')->download($filePath);
    }

    public function download(Document $document)
    {
        $this->checkAccess($document);

        return $this->downloadFile($document->file_path);
    }

    public function downloadSigned(Document $document)
    {
        $this->checkAccess($document);

        return $this->downloadFile($document->signed_file_path);
    }
}
