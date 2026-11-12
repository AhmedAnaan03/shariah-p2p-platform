<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Uploads with real file content, so the type checks in the tests read actual bytes (the
 * way they do for a real upload), not just the file name.
 *
 * UploadedFile::fake() reports a MIME type taken from the name, which would make a PHP file
 * called "id.pdf" look like a PDF; these helpers don't, so they prove the content check works.
 */
trait MakesKycFiles
{
    /**
     * A minimal PDF, padded to $bytes so size limits can be tested to the byte.
     */
    protected function pdfFile(string $name = 'id-card.pdf', int $bytes = 2048): UploadedFile
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $tail = "\n%%EOF\n";

        return $this->fileWithContent($name, $pdf.str_repeat('0', max(0, $bytes - strlen($pdf) - strlen($tail))).$tail);
    }

    /**
     * A real 1×1 PNG image.
     */
    protected function pngFile(string $name = 'id-card.png'): UploadedFile
    {
        return $this->fileWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    /**
     * The start of a JPEG (JFIF) image: enough for the server to recognise it as image/jpeg.
     */
    protected function jpgFile(string $name = 'id-card.jpg'): UploadedFile
    {
        return $this->fileWithContent($name, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00".str_repeat("\x00", 64)."\xFF\xD9");
    }

    /**
     * Any content under any name, e.g. PHP code called "id-card.pdf". $error simulates PHP
     * refusing the upload (UPLOAD_ERR_INI_SIZE = larger than upload_max_filesize).
     */
    protected function fileWithContent(string $name, string $content, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kyc-test-');
        file_put_contents($path, $content);

        // test: true marks it as a test upload, so Laravel doesn't require it to have come
        // through a real HTTP upload (is_uploaded_file).
        return new UploadedFile($path, $name, null, $error, true);
    }
}
