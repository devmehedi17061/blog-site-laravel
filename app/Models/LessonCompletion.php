<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonCompletion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'lesson_id', 'course_id'];
}
