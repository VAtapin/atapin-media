<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookReview extends Model {
    protected $guarded=[];
    protected function casts(): array{return ['rating'=>'integer'];}
    public function user(){return $this->belongsTo(User::class);}
    public function product(){return $this->belongsTo(Product::class);}
}
