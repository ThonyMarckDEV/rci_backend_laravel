<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'idUsuario'; 
    public $timestamps = false;

    protected $fillable = [
     'rol', 'nombres', 'apellidos', 'correo', 'password', 'status','estado','fecha_creado',
    ];

    protected $hidden = ['password'];

    // JWT: Identificador del token
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {

        return [
            'idUsuario' => $this->idUsuario,
            'nombres' => $this->nombres,
            'correo' => $this->correo,
            'rol' => $this->rol,
        ];
    }
    // Relación con ActividadUsuario
    public function activity()
    {
        return $this->hasOne(ActividadUsuario::class, 'idUsuario'); // Cambiado a ActividadUsuario
    }
}
