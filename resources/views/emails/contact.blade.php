<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo mensaje de contacto - RCI Muebles</title>
    <style>
        /* Fuerza el fondo del body a blanco */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #ffffff !important; /* Fondo blanco forzado */
        }

        /* Contenedor principal con imagen de fondo */
        .email-container {
            background-image: url('https://talararci.thonymarckdev.online/storage/imagenes/logo/fondoblanco.jpg');
            background-size: cover; /* Ajusta la imagen al tamaño del contenedor */
            background-position: center; /* Centra la imagen */
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
            position: relative; /* Para posicionar el contenido encima */
        }

        /* Estilos del encabezado */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #E4A853;
            padding-bottom: 20px;
        }

        .logo {
            width: 150px;
            height: auto;
        }

        h1 {
            color: #8B4513;
            font-size: 24px;
            margin: 20px 0;
        }

        /* Contenido principal */
        .content {
            background-color: transparent; /* Fondo transparente */
            padding: 20px;
            border-radius: 8px; /* Bordes redondeados */
        }

        .field {
            margin-bottom: 20px;
        }

        .label {
            color: #E4A853;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        .value {
            color: #333;
            background-color: transparent; /* Fondo transparente */
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #E4A853; /* Borde naranja a la izquierda */
            border: 1px solid #E4A853; /* Borde naranja alrededor */
        }

        .message-content {
            white-space: pre-line;
        }

        /* Pie de página */
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E4A853;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
   <!-- Card con imagen de fondo y contenido superpuesto -->
    <div class="email-container">
        <div class="header">
            <!-- Logo -->
            <img src="https://talararci.thonymarckdev.online/storage/imagenes/logo/rcilogofondoblanco.jpg" alt="RCI Muebles" class="logo">
            <h1>Nuevo mensaje de contacto</h1>
        </div>

        <div class="content">
            <div class="field">
                <span class="label">Nombre:</span>
                <div class='value' style='color: #000;'>{{ $name }}</div> <!-- Negro explícito -->
            </div>

            <div class="field">
                <span class="label">Correo:</span>
                <div class="value">{{ $email }}</div> <!-- Mantiene el color original -->
            </div>

            <div class="field">
                <span class="label">Mensaje:</span>
                <div class='value' message-content style='color: #000;'>{{ $messageContent }}</div> <!-- Negro explícito -->
            </div>
        </div>


        <!-- Pie de página -->
        <div class="footer">
            <p>Este es un mensaje automático de RCI Muebles</p>
            <p>Calidad sin límites a cómodos precios</p>
        </div>
    </div>
</body>
</html>