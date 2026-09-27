<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Design adds a site width. Content written before it spelled
 * that width out as "1100px" — the page sections' content width, and the
 * headers' and footers' CSS. Those become var(--site-width), so they follow
 * the setting; at the default (1100px) nothing looks any different.
 *
 * Only the exact old site width is touched: any other width someone chose
 * stays theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap('1100px', 'var(--site-width)');
    }

    public function down(): void
    {
        $this->swap('var(--site-width)', '1100px');
    }

    private function swap(string $from, string $to): void
    {
        foreach (DB::table('sections')->where('style', 'like', '%max_width%')->get(['id', 'style']) as $row) {
            $style = json_decode((string) $row->style, true);
            if (is_array($style) && ($style['max_width'] ?? null) === $from) {
                $style['max_width'] = $to;
                DB::table('sections')->where('id', $row->id)->update(['style' => json_encode($style)]);
            }
        }

        foreach (DB::table('site_parts')->where('css', 'like', '%max-width: '.$from.'%')->get(['id', 'css']) as $row) {
            DB::table('site_parts')->where('id', $row->id)->update([
                'css' => str_replace('max-width: '.$from, 'max-width: '.$to, (string) $row->css),
            ]);
        }
    }
};
