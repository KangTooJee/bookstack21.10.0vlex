<?php

namespace BookStack\Entities\Models;

// Original uploaded bytes live directly beside pages in a real Book folder.
class AttachedFile extends \BookStack\Model
{
    protected $table = 'vlex_book_files';
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected $casts = ['warnings' => 'array'];

    public function book(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
