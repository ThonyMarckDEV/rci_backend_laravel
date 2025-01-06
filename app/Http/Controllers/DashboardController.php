<?php

namespace App\Http\Controllers;

use App\Models\ImagenModelo;
use App\Models\Talla;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;
use App\Mail\NotificacionPagoCompletado;
use App\Mail\NotificacionPedidoEliminado;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use App\Models\Facturacion;
use App\Models\Modelo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class DashboardController extends Controller
{
    public function obtenerInfoAdmins()
    {
        $activeUsers = Usuario::where('estado', 'activo')->count();
        $inactiveUsers = Usuario::where('estado', 'inactivo')->count();
        $totalUsers = $activeUsers + $inactiveUsers;

        return response()->json([
            'stats' => [
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'total_users' => $totalUsers
            ],
            'message' => 'User statistics retrieved successfully'
        ]);
    }

    public function getLogs(Request $request)
    {
        // Obtener el rol del usuario autenticado
        $userRole = auth()->user()->rol;
    
        $query = LogUser::select('logs.*', 'usuarios.nombres', 'usuarios.rol')
            ->join('usuarios', 'logs.idUsuario', '=', 'usuarios.idUsuario');
    
        // Filtros
        if ($request->fecha_desde) {
            $query->whereDate('logs.fecha', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('logs.fecha', '<=', $request->fecha_hasta);
        }
        if ($request->usuario) {
            $query->where('usuarios.nombres', 'LIKE', '%' . $request->usuario . '%');
        }
        if ($request->accion) {
            $query->where('logs.accion', 'LIKE', '%' . $request->accion . '%');
        }
    
        // Filtrar logs según el rol seleccionado en el filtro
        if ($request->rol) {
            $query->where('usuarios.rol', $request->rol); // Filtra por el rol seleccionado
        } else {
            // Si no se selecciona un rol, aplicar el filtro según el rol del usuario autenticado
            if ($userRole === 'admin') {
                $query->where('usuarios.rol', 'admin'); // Solo logs de admin
            } elseif ($userRole === 'superadmin') {
                $query->whereIn('usuarios.rol', ['superadmin', 'admin']); // Logs de superadmin y admin
            }
        }
    
        $logs = $query->orderBy('logs.fecha', 'desc')->paginate(10);
    
        // Obtener usuarios según el rol del usuario autenticado
        $usuariosQuery = Usuario::select('idUsuario', 'nombres', 'rol');
        if ($userRole === 'admin') {
            $usuariosQuery->where('rol', 'admin'); // Solo usuarios con rol admin
        } elseif ($userRole === 'superadmin') {
            $usuariosQuery->whereIn('rol', ['superadmin', 'admin']); // Usuarios con rol superadmin y admin
        }
    
        return response()->json([
            'logs' => $logs,
            'usuarios' => $usuariosQuery->get()
        ]);
    }

}
