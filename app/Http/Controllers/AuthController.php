<?php

namespace App\Http\Controllers;

use App\Models\ActividadUsuario;
use App\Models\Usuario;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


/**
* @OA\Info(
*    title="ECOMMERCE API DOCUMENTATION", 
*    version="1.0",
*    description="API DOCUMENTATION"
* )
*
* @OA\Server(url="http://localhost:8000")
*/
class AuthController extends Controller
{
    /**
     * Login
     * @OA\Post(
     *     path="/api/login",
     *     tags={"AUTH CONTROLLER"},
     *     summary="Login de usuario",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="correo", type="string", example="usuario@dominio.com"),
     *             @OA\Property(property="password", type="string", example="contraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token generado con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al generar el token"
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validar que el correo y la contraseña están presentes
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
    
        // Obtener las credenciales de correo y contraseña
        $credentials = [
            'correo' => $request->input('correo'),
            'password' => $request->input('password')
        ];
    
        try {
            // Buscar el usuario por correo
            $usuario = Usuario::where('correo', $credentials['correo'])->first();
    
            // Verificar si el usuario existe
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
    
            // Intentar autenticar y generar el token JWT usando el campo 'correo'
            if (!$token = JWTAuth::attempt(['correo' => $credentials['correo'], 'password' => $credentials['password']])) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }
    
            // Obtener el dispositivo
            $dispositivo = $this->obtenerDispositivo();
    
            // Verificar si ya existe un registro en la tabla 'actividad_usuario' para este usuario
            $actividad = ActividadUsuario::where('idUsuario', $usuario->idUsuario)->first();
    
            if (!$actividad) {
                // Si no existe, crear un nuevo registro
                ActividadUsuario::create([
                    'idUsuario' => $usuario->idUsuario,
                    'last_activity' => now(),
                    'dispositivo' => $dispositivo,
                    'jwt' => $token,
                ]);
            } else {
                // Si ya existe, verificar si el dispositivo ha cambiado
                if ($actividad->dispositivo !== $dispositivo) {
                    // Si el dispositivo ha cambiado, invalidar el token anterior (ponerlo como null)
                    $actividad->update([
                        'jwt' => null,  // Invalida el token anterior
                    ]);
    
                    // Crear un nuevo registro de actividad con el nuevo token y dispositivo
                    $actividad->update([
                        'last_activity' => now(),
                        'dispositivo' => $dispositivo,
                        'jwt' => $token,  // Asigna el nuevo token
                    ]);
                } else {
                    // Si el dispositivo es el mismo, solo actualizar el token y la actividad
                    $actividad->update([
                        'last_activity' => now(),
                        'jwt' => $token,  // Actualiza el token
                    ]);
                }
            }
    
            // Actualizar el estado del usuario a "loggedOn"
            $usuario->update(['status' => 'loggedOn']);
    
            // Obtener el ID del usuario autenticado desde el token
            $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado
    
            // Obtener el nombre completo del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
            // Definir la acción y mensaje para el log
            $accion = "$nombreUsuario inició sesión desde el dispositivo: $dispositivo";
    
            // Llamada a la función agregarLog para registrar el log
            $this->agregarLog($usuarioId, $accion);
    
            return response()->json(compact('token'));
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }
    }

    // Función para obtener el dispositivo
    private function obtenerDispositivo()
    {
        return request()->header('User-Agent');  // Obtiene el User-Agent del encabezado de la solicitud
    }



    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión del usuario",
     *     description="Este endpoint se utiliza para cerrar sesión de un usuario y revocar su token JWT.",
     *     operationId="logout",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"idUsuario"},
     *             @OA\Property(property="idUsuario", type="integer", description="ID del usuario que desea cerrar sesión.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario deslogueado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario deslogueado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No se encontró el usuario.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo encontrar el usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al desloguear al usuario.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No se pudo desloguear al usuario.")
     *         )
     *     ),
     * )
     */
    public function logout(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);
    
        $user = Usuario::where('idUsuario', $request->idUsuario)->first();
    
        if ($user) {
            try {
                // Actualizar el estado del usuario a "loggedOff"
                $user->status = 'loggedOff';
                $user->save();
    
                // Obtener el nombre completo del usuario
                $nombreUsuario = $user->nombres . ' ' . $user->apellidos;
    
                // Definir la acción y mensaje para el log
                $accion = "$nombreUsuario cerró sesión";
    
                // Llamada a la función agregarLog para registrar el log
                $this->agregarLog($user->idUsuario, $accion);
    
                return response()->json(['success' => true, 'message' => 'Usuario deslogueado correctamente'], 200);
            } catch (JWTException $e) {
                return response()->json(['error' => 'No se pudo desloguear al usuario'], 500);
            }
        }
    
        return response()->json(['success' => false, 'message' => 'No se pudo encontrar el usuario'], 404);
    }

    public function refreshToken(Request $request)
    {
        try {
            $oldToken = JWTAuth::getToken();  // Obtener el token actual
            
            Log::info('Refrescando token: Token recibido', ['token' => (string) $oldToken]);
            
            // Decodificar el token para obtener el payload
            $decodedToken = JWTAuth::getPayload($oldToken);  // Utilizamos getPayload para obtener el payload
            $userId = $decodedToken->get('idUsuario');  // Usamos get() para acceder a 'idUsuario'
            
            // Refrescar el token
            $newToken = JWTAuth::refresh($oldToken);
            
            // Actualizar el campo jwt en la tabla actividad_usuario
            $actividadUsuario = ActividadUsuario::updateOrCreate(
                ['idUsuario' => $userId],  // Si ya existe, se actualizará por el idUsuario
                ['jwt' => $newToken]  // Actualizar el campo jwt con el nuevo token
            );
            
            Log::info('JWT actualizado en la actividad del usuario', ['userId' => $userId, 'jwt' => $newToken]);
            
            return response()->json(['accessToken' => $newToken], 200);
        } catch (JWTException $e) {
            Log::error('Error al refrescar el token', ['error' => $e->getMessage()]);
            
            return response()->json(['error' => 'No se pudo refrescar el token'], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/update-activity",
     *     summary="Actualizar la última actividad del usuario",
     *     description="Este endpoint actualiza la fecha de la última actividad del usuario especificado.",
     *     operationId="updateLastActivity",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuya última actividad se actualizará.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Actividad actualizada correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Last activity updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="ID de usuario requerido")
     *         )
     *     )
     * )
     */
    public function updateLastActivity(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);

        $user = Usuario::find($request->idUsuario);
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        
        $user->activity()->updateOrCreate(
            ['idUsuario' => $user->idUsuario],
            ['last_activity' => now()]
        );
        
        return response()->json(['message' => 'Last activity updated'], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/check-status",
     *     summary="Verificar el estado del usuario",
     *     description="Este endpoint verifica el estado del usuario, si está conectado o desconectado.",
     *     operationId="checkStatus",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuyo estado se desea verificar.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="El usuario está activo.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="ID de usuario no proporcionado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="ID de usuario no proporcionado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuario desconectado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Usuario desconectado")
     *         )
     *     )
     * )
     */
    // Endpoint para verificar el estado del usuario y el token
    public function checkStatus(Request $request)
    {
        // Validar que el idUsuario esté presente
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);
    
        $idUsuario = $request->input('idUsuario');
        $token = $request->bearerToken(); // Obtener el token JWT del encabezado Authorization
    
        // Buscar el registro de actividad del usuario en la base de datos
        $actividadUsuario = ActividadUsuario::where('idUsuario', $idUsuario)->first();
    
        // Si no hay un registro de actividad, devolver un error
        if (!$actividadUsuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
    
        // Verificar si el token en la base de datos es diferente al token actual
        if ($actividadUsuario->jwt !== $token) {
            return response()->json(['error' => 'El token no coincide con el almacenado'], 403);
        }
    
        // Si el token es válido, devolver el estado y el token actual
        return response()->json([
            'status' => 'success',
            'token' => $actividadUsuario->jwt  // Devuelves el token almacenado en la base de datos
        ]);
    }
    

    /**
     * @OA\Post(
     *     path="/api/send-message",
     *     summary="Enviar mensaje de contacto",
     *     description="Este endpoint permite a los usuarios enviar un mensaje de contacto al administrador.",
     *     operationId="sendContactEmail",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del mensaje de contacto",
     *         @OA\JsonContent(
     *             required={"name", "email", "message"},
     *             @OA\Property(property="name", type="string", description="Nombre del remitente", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="Correo electrónico del remitente", example="johndoe@example.com"),
     *             @OA\Property(property="message", type="string", description="Mensaje de contacto", example="Hola, tengo una consulta sobre los productos.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mensaje enviado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example="Mensaje enviado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error en los datos enviados.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El nombre es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al enviar el mensaje.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al enviar el mensaje. Inténtalo más tarde.")
     *         )
     *     )
     * )
     */
     public function sendContactEmail(Request $request)
     {
         $request->validate([
             'name' => 'required|string|max:255',
             'email' => 'required|email',
             'message' => 'required|string',
         ]);
 
         // Configura los datos del correo
         $data = [
             'name' => $request->name,
             'email' => $request->email,
             'messageContent' => $request->message,
         ];
 
         // Envía el correo
         Mail::send('emails.contact', $data, function($message) use ($request) {
             $message->to('destinatario@example.com', 'Administrador')
                     ->subject('Nuevo mensaje de contacto');
             $message->from($request->email, $request->name);
         });
 
         return response()->json(['success' => 'Mensaje enviado correctamente.']);
     }

    // Función para agregar un log directamente desde el backend
    public function agregarLog($usuarioId, $accion)
    {
        // Obtener el usuario por id
        $usuario = Usuario::find($usuarioId);

        if ($usuario) {
            // Crear el log
            $log = LogUser::create([
                'idUsuario' => $usuario->idUsuario,
                'nombreUsuario' => $usuario->nombres . ' ' . $usuario->apellidos,
                'rol' => $usuario->rol,
                'accion' => $accion,
                'fecha' => now(),
            ]);

            return response()->json(['message' => 'Log agregado correctamente', 'log' => $log], 200);
        }

        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

}
