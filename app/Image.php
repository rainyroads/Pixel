<?php namespace Pixel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model {

    /**
     * Guarded attributes
     *
     * @var array
     */
	protected $guarded = ['id', 'deleted_at'];

}