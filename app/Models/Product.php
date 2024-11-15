<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'title',
        'body_html',
        'vendor',
        'product_type',
        'variants',
        'image',
        'collection_title',
        'collection_desc',
    ];
}
