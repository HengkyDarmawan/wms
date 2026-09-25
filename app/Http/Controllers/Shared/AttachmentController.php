<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran dialirkan lewat route berizin (NFR-14, A-68): boleh dibuka bila
 * user boleh melihat dokumen pemiliknya; dokumen di luar cakupan = 404.
 */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment, StoreUpload $files): StreamedResponse
    {
        $pemilik = $attachment->owner();
        abort_if($pemilik === null, 404);
        $this->authorize('view', $pemilik);

        abort_unless($files->exists($attachment->path), 404);

        return $files->stream($attachment->path);
    }
}
