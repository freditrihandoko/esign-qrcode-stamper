<?php

namespace App\Http\Controllers;


use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\QrCode;



class PdfStampController extends Controller
{
    public function show($qrCode)
    {
        $qrCode = QrCode::with('document')->findOrFail($qrCode);
        return view('pdfstamp', compact('qrCode'));
    }

    public function post(Request $request, $qrCode)
    {
        $data = $request->all();
        $qrCode = QrCode::with('document')->findOrFail($qrCode);

        // Create new FPDI instance with custom settings
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Get file paths
        $qrPath = Storage::disk('public')->path($qrCode->qr_code_path);
        $file = Storage::disk('public')->path($qrCode->document->file_path);

        // Set source file
        $pageCount = $pdf->setSourceFile($file);

        // Calculate positions (adjusted scale from 1.5 to 1)
        $stampX = $data['stampX'] / 1.5;
        $stampY = $data['stampY'] / 1.5;
        $canvasHeight = $data['canvasHeight'] / 1.5;
        $canvasWidth = $data['canvasWidth'] / 1.5;
        $pageNumber = $data['pageNumber'];

        // Loop through pages
        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);

            // Add page with specific settings
            $pdf->AddPage(
                $size['orientation'],
                [$size['width'], $size['height']]
            );

            // Important: Set margins to 0
            $pdf->SetMargins(0, 0, 0);

            // Disable auto-page-break
            $pdf->SetAutoPageBreak(false, 0);

            // Use template
            $pdf->useTemplate($template, 0, 0, null, null, true);

            // Add QR code only on specified page
            if ($i == $pageNumber) {
                // Calculate real positions with scale adjustment
                $widthDiffPercent = ($canvasWidth - $size['width']) / $canvasWidth * 100;
                $heightDiffPercent = ($canvasHeight - $size['height']) / $canvasHeight * 100;

                $realXPosition = $stampX - ($widthDiffPercent * $stampX / 100);
                $realYPosition = $stampY - ($heightDiffPercent * $stampY / 100);

                // Add QR code with preserved alpha channel
                $pdf->Image(
                    $qrPath,
                    $realXPosition,
                    $realYPosition,
                    20.46,
                    20.46,
                    'PNG'
                );
            }
        }

        // Save the output
        $outputPath = 'documents/signed_documents/' . uniqid() . '_signed.pdf';
        Storage::disk('public')->makeDirectory(dirname($outputPath));

        $signedPath = Storage::disk('public')->path($outputPath);
        $pdf->Output($signedPath, 'F');

        // Update document
        $qrCode->document->update([
            'signed_file_path' => $outputPath,
            'pdf_generated_at' => now(),
            'status' => 'signed',
        ]);

        return redirect()->route('qrcodes.show', $qrCode->id)
            ->with('qrCodePlaced', true);
    }
}
