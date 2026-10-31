<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de contraseña</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #334155;
            -webkit-text-size-adjust: 100%;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #691C32 0%, #520B1D 100%);
            padding: 30px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #fecdd3;
            font-weight: 500;
        }
        .gold-bar {
            height: 4px;
            background: linear-gradient(90deg, #D4AF37 0%, #F59E0B 50%, #D4AF37 100%);
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .paragraph {
            font-size: 14px;
            color: #334155;
            margin-bottom: 20px;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background-color: #691C32;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 6px -1px rgba(105, 28, 50, 0.3);
        }
        .notice-card {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .notice-card p {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #92400e;
            font-weight: 600;
        }
        .notice-card ul {
            margin: 0;
            padding-left: 18px;
            font-size: 12px;
            color: #78350f;
        }
        .notice-card li {
            margin-bottom: 4px;
        }
        .alternative-link {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
        }
        .alternative-link a {
            color: #691C32;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #94a3b8;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado Institucional TecNM -->
        <div class="header">
            <h1>TECNM &bull; CAMPUS PONIENTE</h1>
            <p>Sistema Web de Objetos Perdidos</p>
        </div>
        <div class="gold-bar"></div>

        <!-- Contenido Principal -->
        <div class="content">
            <div class="greeting">
                Hola{{ isset($user->name) ? ', ' . $user->name : '' }}:
            </div>

            <p class="paragraph">
                Has recibido este mensaje porque se solicitó recuperar el acceso al sistema de Objetos Perdidos del Tec Campus Poniente asociado a tu cuenta institucional.
            </p>

            <!-- Botón de Acción -->
            <div class="btn-container">
                <a href="{{ $url }}" class="btn" target="_blank" rel="noopener">
                    Restablecer contraseña
                </a>
            </div>

            <!-- Indicaciones de Seguridad y Vigencia -->
            <div class="notice-card">
                <p>Información importante de seguridad:</p>
                <ul>
                    <li>El enlace vence a los <strong>15 minutos</strong> de haberse emitido.</li>
                    <li>Por motivos de seguridad institucional, este enlace puede utilizarse <strong>una sola vez</strong>.</li>
                    <li>No compartas este enlace con ninguna persona.</li>
                </ul>
            </div>

            <p class="paragraph">
                Si tú no solicitaste este cambio, puedes ignorar este mensaje con total seguridad. Tu contraseña actual permanecerá intacta y tu cuenta continuará protegida.
            </p>

            <!-- Enlace alternativo en texto plano -->
            <div class="alternative-link">
                Si tienes problemas para hacer clic en el botón "Restablecer contraseña", copia y pega la siguiente URL en tu navegador web:
                <br>
                <a href="{{ $url }}">{{ $url }}</a>
            </div>
        </div>

        <!-- Pie de Página Institucional -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} Instituto Tecnológico de Mérida &bull; Campus Poniente</p>
            <p>Este es un mensaje institucional generado automáticamente, por favor no respondas a este correo.</p>
        </div>
    </div>
</body>
</html>

