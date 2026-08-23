<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillableService extends Model
{
    protected $fillable = ['hospital_id', 'billable_service_category_id', 'department_id', 'public_site_item_id', 'code', 'name', 'description', 'is_tax_exempt', 'tax_rate_basis_points', 'is_discount_eligible', 'is_active'];

    protected function casts(): array
    {
        return ['is_tax_exempt' => 'boolean', 'is_discount_eligible' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BillableServiceCategory::class, 'billable_service_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function publicSiteItem(): BelongsTo
    {
        return $this->belongsTo(PublicSiteItem::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class)->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }
}
