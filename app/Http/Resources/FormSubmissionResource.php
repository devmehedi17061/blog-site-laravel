<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FormSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => $this->summary(),
            'preview' => $this->preview(),
            'answers' => $this->answers(),

            'unread' => $this->isUnread(),
            'read_at' => $this->read_at?->format('d M Y, H:i'),

            'ip_address' => $this->ip_address,
            'received' => $this->created_at?->format('d M Y, H:i'),
            'received_short' => $this->shortDate(),

            // Only loaded by the inbox, which mixes every form together.
            'form' => $this->whenLoaded('form', fn () => [
                'id' => $this->form->id,
                'name' => $this->form->name,
                'slug' => $this->form->slug,
            ]),
        ];
    }

    /** Today shows a time; anything older shows a date, as a mail client does. */
    private function shortDate(): string
    {
        if (! $this->created_at) {
            return '';
        }

        return $this->created_at->isToday()
            ? $this->created_at->format('H:i')
            : $this->created_at->format('d M');
    }

    /**
     * Label, value and — for an upload — where the file went.
     *
     * Read against the form as it is *now*, so a field renamed since this
     * submission arrived falls back to the key that was stored with it
     * rather than disappearing from the record.
     */
    private function answers(): array
    {
        $fields = $this->form?->fields ?? collect();
        $labels = $fields->pluck('label', 'name');
        $types = $fields->pluck('type', 'name');

        $answers = [];

        foreach ($this->payload ?? [] as $name => $value) {
            $isFile = ($types[$name] ?? null) === 'file';

            $answers[] = [
                'name' => $name,
                'label' => $labels[$name] ?? $name,
                'value' => is_array($value) ? implode(', ', $value) : (string) $value,
                'url' => $isFile && filled($value) ? Storage::disk('public')->url($value) : null,
            ];
        }

        return $answers;
    }
}
