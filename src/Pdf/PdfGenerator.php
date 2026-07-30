<?php

declare(strict_types=1);

namespace Toolreport\Core\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;

class PdfGenerator
{
    /**
     * Save a pre-generated PDF binary as a PdfDocument to storage.
     *
     * Creates the PdfDocument record, persists the binary to the configured
     * disk, and returns the document with status 'done'.
     *
     * @param PdfTemplate $template The template model.
     * @param string $pdfBinary The raw PDF binary content.
     * @param string $title The document title.
     * @param array $data The data used for generation.
     * @return PdfDocument
     * @throws PdfGenerationException
     */
    public function saveBinary(PdfTemplate $template, string $pdfBinary, string $title, array $data = []): PdfDocument
    {
        $disk = config('pdf-designer.storage.disk', 'local');
        $storagePath = config('pdf-designer.storage.path', 'pdf-documents');

        $document = new PdfDocument([
            'pdf_template_id' => $template->id,
            'title' => $title,
            'data' => $data,
            'status' => 'generating',
        ]);
        $document->save();

        try {
            $filename = sprintf(
                '%s/%s_%s.pdf',
                $storagePath,
                \Illuminate\Support\Str::slug($template->name),
                $document->id
            );

            Storage::disk($disk)->put($filename, $pdfBinary);

            $document->file_path = $filename;
            $document->file_size = strlen($pdfBinary);
            $document->status = 'done';
            $document->generated_at = now();
            $document->save();

            return $document;

        } catch (\Exception $e) {
            $document->status = 'failed';
            $document->error_message = $e->getMessage();
            $document->save();

            throw PdfGenerationException::generationError($e->getMessage());
        }
    }

    /**
     * Stream the PDF content for a given document.
     *
     * @param PdfDocument $document
     * @return Response
     * @throws PdfGenerationException
     */
    public function download(PdfDocument $document): Response
    {
        if (!$document->isAvailable()) {
            throw PdfGenerationException::renderFailed(
                "Document #{$document->id} is not available (status: {$document->status})."
            );
        }

        $disk = config('pdf-designer.storage.disk', 'local');

        if (!Storage::disk($disk)->exists($document->file_path)) {
            throw PdfGenerationException::storageError(
                "File not found at path: {$document->file_path}"
            );
        }

        $content = Storage::disk($disk)->get($document->file_path);

        return response()->make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$document->title}.pdf\"",
        ]);
    }
}
