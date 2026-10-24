<?php

namespace App\Models;

use Database\Factories\MembershipBenefitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $icon
 * @property string $text
 * @property int $sort_order
 */
#[Fillable(['icon', 'text', 'sort_order'])]
class MembershipBenefit extends Model
{
    /** @use HasFactory<MembershipBenefitFactory> */
    use HasFactory;
}
