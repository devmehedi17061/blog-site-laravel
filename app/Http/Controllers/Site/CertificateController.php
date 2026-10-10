<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A printable certificate for a finished course. "Save as PDF" in the
 * browser's print dialog makes the file; no PDF library is needed.
 */
class CertificateController extends Controller
{
    public function show(Request $request, Enrollment $enrollment): View|RedirectResponse
    {
        abort_unless(Lms::setting('certificates'), 404);

        $user = $request->user();
        abort_unless($enrollment->user_id === $user->id || $user->can('view', $enrollment), 403);

        if (! $enrollment->isActive() || ! $enrollment->completed_at) {
            return redirect()->route('student.dashboard')
                ->with('status', __('The certificate is ready once every lesson is complete.'));
        }

        $enrollment->load(['student:id,name', 'course' => fn ($q) => $q->with('instructor:id,name')]);

        return view('frontend.student.certificate', [
            'enrollment' => $enrollment,
            'text' => (string) Lms::setting('certificate_text'),
            'signer' => (string) Lms::setting('certificate_signer') ?: $enrollment->course?->instructor?->name,
            // Checkable by anyone at /certificates/verify.
            'serial' => $enrollment->certificateSerial(),
        ]);
    }

    /**
     * Public: anyone with a certificate number — an employer, say — can
     * check that it is genuine and see whose it is.
     */
    public function verify(Request $request): View
    {
        $serial = trim((string) $request->query('serial', ''));

        return view('frontend.certificate-verify', [
            'serial' => $serial,
            'enrollment' => $serial !== '' ? Enrollment::fromCertificateSerial($serial) : null,
        ]);
    }
}
