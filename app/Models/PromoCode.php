<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PromoCode extends Model {
 protected $guarded=[];
 protected function casts(): array {return ['is_active'=>'boolean','starts_at'=>'datetime','expires_at'=>'datetime','value'=>'decimal:2','min_amount'=>'decimal:2','max_discount'=>'decimal:2'];}
}
