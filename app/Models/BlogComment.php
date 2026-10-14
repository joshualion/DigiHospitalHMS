<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    protected $fillable = ['hospital_id', 'public_site_item_id', 'name', 'email', 'body', 'status', 'moderation_note', 'moderated_by', 'moderated_at'];
    protected $hidden = ['email'];
    protected function casts(): array { return ['moderated_at' => 'datetime']; }
    public function post(): BelongsTo { return $this->belongsTo(PublicSiteItem::class, 'public_site_item_id'); }
}
