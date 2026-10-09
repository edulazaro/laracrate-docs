<?php

namespace EduLazaro\LaracrateDocs\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/** Hace de usuario y de organización: al paquete le da igual qué sean. */
class TestActor extends Model
{
    protected $table = 'test_actors';

    protected $fillable = ['name'];
}
