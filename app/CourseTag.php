<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class CourseTag extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'courses_tags';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['id', 'tag_type', 'created_at', 'updated_at'];

    /**
     * @return BelongsToMany
     */
    public function courses() {
        return $this
            ->belongsToMany('App\Course','tag_course', 'tag_id', 'course_id');
    }

    //TODO 3 most tagged courses for each tag
    /**
     * @return mixed
     */
//    public function topCourses()
//    {
//        return $this->courses()->selectRaw('count(tag_course.tag_id) as amount')->groupBy('tag_course.tag_id');
//    }
}