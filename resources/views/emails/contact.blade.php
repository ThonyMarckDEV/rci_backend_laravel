<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo mensaje de contacto - RCI Muebles</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
            background-color: #FFF9E6;
            color: #2c2c2c;
        }

        .outer-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(228, 168, 83, 0.1);
        }

        .email-container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 24px;
        }

        h1 {
            font-weight: 300;
            font-size: 24px;
            color: #1a1a1a;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .content {
            margin: 32px 0;
        }

        .field {
            margin-bottom: 24px;
        }

        .field:last-child {
            margin-bottom: 0;
        }

        .label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }

        .value {
            font-size: 15px;
            padding: 16px;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #2c2c2c;
            line-height: 1.6;
        }

        .message-content {
            white-space: pre-line;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 1px solid #f0f0f0;
        }

        .footer p {
            color: #888;
            font-size: 13px;
            margin: 4px 0;
        }

        .footer p:last-child {
            color: #aaa;
            font-style: italic;
        }

        @media (max-width: 480px) {
            .outer-container {
                padding: 24px;
            }
            
            .header {
                margin-bottom: 32px;
            }
            
            .content {
                margin: 24px 0;
            }
        }
    </style>
</head>
<body>
    <div class="outer-container">
        <div class="email-container">
            <div class="header">
                <img src="https://rci-eosin.vercel.app/static/media/logorci.f5f2abc26d20172a9a36.png" alt="RCI Muebles" class="logo">
                <h1>Nuevo mensaje de contacto</h1>
            </div>
            
            <div class="content">
                <div class="field">
                    <span class="label">Nombre</span>
                    <div class="value">{{ $name }}</div>
                </div>
                
                <div class="field">
                    <span class="label">Correo electrónico</span>
                    <div class="value">{{ $email }}</div>
                </div>
                
                <div class="field">
                    <span class="label">Mensaje</span>
                    <div class="value message-content">{{ $messageContent }}</div>
                </div>
            </div>

            <div class="footer">
                <p>Este es un mensaje automático de RCI Muebles</p>
                <p>Calidad sin límites a cómodos precios</p>
            </div>
        </div>
    </div>
</body>
</html>