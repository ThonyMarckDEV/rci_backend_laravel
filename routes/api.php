<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperAdminController;

//RUTAS
//================================================================================================
        //RUTAS  AUTH

        //RUTA PARA QUE LOS USUAIOS SE LOGEEN POR EL CONTROLLADOR AUTHCONTROLLER
        Route::post('login', [AuthController::class, 'login']);

        Route::post('/send-message', [AuthController::class, 'sendContactEmail']);

        //PARA HOME
   
       Route::get('/listarProductosCatalogo', [SuperAdminController::class, 'listarProductosCatalogo']);

       Route::get('/listarCategorias', [SuperAdminController::class, 'listarCategorias']);

       Route::get('/listarCategoriasFiltrador', [SuperAdminController::class, 'listarCategoriasFiltrador']);

       Route::get('/productos/{id}/caracteristicas', [SuperAdminController::class, 'obtenerCaracteristicas']);

//================================================================================================
    //RUTAS  AUTH PROTEGIDAS par todos los roles

    Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () {

        Route::post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('update-activity', [AuthController::class, 'updateLastActivity']);

        Route::post('/check-status', [AuthController::class, 'checkStatus']);

        
        Route::get('/categoriasproductos', [SuperAdminController::class, 'listarCategoriasproductos']);
        Route::post('/agregarProductos', [SuperAdminController::class, 'agregarProducto']);
        Route::post('/editarModeloyImagen/{id}', [SuperAdminController::class, 'editarModeloyImagen']);
        Route::delete('/eliminarImagenModelo/{id}', [SuperAdminController::class, 'eliminarImagenModelo']);
        Route::get('/listarProductos', [SuperAdminController::class, 'listarProductos']);
        Route::put('/actualizarProducto/{id}', [SuperAdminController::class, 'actualizarProducto']);
        Route::put('/cambiarEstadoProducto/{id}',[SuperAdminController::class, 'cambiarEstadoProducto']);
        Route::post('/agregarModelo', [SuperAdminController::class, 'agregarModelo']);
        Route::delete('/EliminarModelo/{idModelo}', [SuperAdminController::class, 'EliminarModelo']);
    });

//================================================================================================
    //RUTAS PROTEGIDAS A
    // RUTAS PARA ADMINISTRADOR VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:superadmin'])->group(function () { 

        // Listar usuarios
        Route::get('/listarUsuariosAdmin', [SuperAdminController::class, 'listarUsuarios']);
        // Editar usuario
        Route::put('/listarUsuariosAdmin/{id}', [SuperAdminController::class, 'editarUsuario']);
        // Cambiar estado de usuario (activo/inactivo)
        Route::patch('/listarUsuariosAdmin/{id}/cambiar-estado', [SuperAdminController::class, 'cambiarEstado']);
        Route::post('/adminAgregar', [SuperAdminController::class, 'agregarUsuario']);
        Route::post('/usuarios/{id}/cambiar-password',[SuperAdminController::class, 'cambiarPassword']);
        Route::put('/password/{id}', [SuperAdminController::class, 'updatePasswordSuperAdmin']);

        Route::get('/listarProductos/{id}', [SuperAdminController::class, 'listarProductos']);

        Route::post('/categorias', [SuperAdminController::class, 'agregarCategorias']);
        Route::get('/obtenerCategorias', [SuperAdminController::class, 'obtenerCategorias']);
        Route::post('/actualizarCategoria/{id}', [SuperAdminController::class, 'actualizarCategoria']);
        Route::put('/cambiarEstadoCategoria/{id}', [SuperAdminController::class, 'cambiarEstadoCategoria']);


        Route::get('/listarUsuarios', [SuperAdminController::class, 'listarUsuarios']);
        Route::get('/obtenerInfoAdmins', [DashboardController::class, 'obtenerInfoAdmins']);
        Route::get('/logs', [DashboardController::class, 'getLogs']);
    });


//================================================================================================

