<?php

namespace App\Http\Resources;

use App\Support\MediaUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'kind' => $this->kind,
            'is_image' => $this->kind === 'image',
            'size' => $this->human_size,
            'bytes' => (int) $this->size,
            'width' => $this->width,
            'height' => $this->height,
            // How many smaller WebP copies the site can serve instead.
            'optimised' => count((array) ($this->variants ?? [])),
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'folder_id' => $this->folder_id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder?->name),
            'uploaded_by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'uploaded' => $this->created_at?->format('d M Y'),

            // Only resolved for the details panel — it costs two queries per file.
            'usage' => $this->when(
                $request->boolean('with_usage'),
                fn () => MediaUsage::for($this->resource)
            ),
        ];
    }
}
