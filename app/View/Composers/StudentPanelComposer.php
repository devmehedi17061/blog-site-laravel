<?php

namespace App\View\Composers;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\Lms;
use Illuminate\View\View;

/**
 * The student panel's sidebar: who is signed in and the small counts beside
 * each entry. Built once per request, for whichever panel page is showing.
 */
class StudentPanelComposer
{
    private ?array $data = null;

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->data ??= [
            'panelUser' => $user,
            'panelCounts' => [
                'courses' => Enrollment::where('user_id', $user->id)->whereIn('status', ['Active', 'Pending'])->whereHas('course')->count(),
                'payments' => Payment::where('user_id', $user->id)->where('status', 'Pending')->count(),
                'certificates' => Lms::setting('certificates')
                    ? Enrollment::where('user_id', $user->id)->where('status', 'Active')->whereNotNull('completed_at')->count()
                    : 0,
                'notifications' => $user->unreadNotifications()->count(),
            ],
        ];

        $view->with($this->data);
    }
}
