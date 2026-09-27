<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The header & footer builder gets its own permission module, "layout".
 *
 * Roles are not reseeded (that would undo changes made under Roles &
 * permissions). Instead every role that may already work on the menu gets
 * the same abilities on the layout: whoever edits the navigation is the
 * natural owner of the bar it sits in.
 */
return new class extends Migration
{
    private const ABILITIES = ['view', 'create', 'update', 'delete'];

    public function up(): void
    {
        $now = now();

        foreach (self::ABILITIES as $ability) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => "layout.{$ability}"],
                ['name' => ucfirst($ability).' Layout', 'group' => 'Builders', 'created_at' => $now, 'updated_at' => $now]
            );

            $layoutId = DB::table('permissions')->where('slug', "layout.{$ability}")->value('id');
            $menuId = DB::table('permissions')->where('slug', "menu.{$ability}")->value('id');

            if (! $menuId) {
                continue;
            }

            $roles = DB::table('permission_role')->where('permission_id', $menuId)->pluck('role_id');

            foreach ($roles as $roleId) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $layoutId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('slug', 'like', 'layout.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
