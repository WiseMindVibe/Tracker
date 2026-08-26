<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogBuffer extends Model
{
    /** @use HasFactory<\Database\Factories\BlogBufferFactory> */
    use HasFactory;

    public $table = 'blogs_buffers';

    public $fillable = ['blog_id', 'buffer_url'];


}
