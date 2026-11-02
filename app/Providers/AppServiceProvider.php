<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Soporte de comparaciones insensibles a mayúsculas y acentos en SQLite para pruebas unitarias/feature (HU-10 / Tarea 10.2)
        $registerSqliteUnaccentLike = function ($connection) {
            if ($connection->getDriverName() === 'sqlite') {
                $pdo = $connection->getPdo();
                if ($pdo && method_exists($pdo, 'sqliteCreateFunction')) {
                    $unaccent = function ($string) {
                        if ($string === null) {
                            return '';
                        }
                        $string = mb_strtolower((string) $string, 'UTF-8');
                        $map = [
                            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
                            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
                            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                            'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Å' => 'a',
                            'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
                            'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i',
                            'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Õ' => 'o',
                            'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u',
                        ];
                        return strtr($string, $map);
                    };

                    $pdo->sqliteCreateFunction('like', function ($pattern, $value) use ($unaccent) {
                        if ($pattern === null || $value === null) {
                            return 0;
                        }
                        $normPattern = $unaccent($pattern);
                        $normValue = $unaccent($value);

                        // Proteger caracteres escapados con marcadores temporales
                        $normPattern = str_replace(['\\\\', '\\%', '\\_'], ["\x01", "\x02", "\x03"], $normPattern);
                        $quoted = preg_quote($normPattern, '/');
                        $quoted = str_replace(['%', '_'], ['.*', '.'], $quoted);
                        $quoted = str_replace(["\x01", "\x02", "\x03"], ['\\\\', '%', '_'], $quoted);

                        return preg_match('/^' . $quoted . '$/us', $normValue) ? 1 : 0;
                    }, 2);
                }
            }
        };

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) use ($registerSqliteUnaccentLike) {
            $registerSqliteUnaccentLike($event->connection);
        });

        // Asegurar que si la conexión por defecto ya fue resuelta o al bootear en SQLite se registre la función
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $registerSqliteUnaccentLike(DB::connection());
            }
        } catch (\Throwable) {
            // Ignorar en comandos de arranque sin base de datos configurada
        }

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expireMinutes = (int) config('auth.passwords.users.expire', 15);

            return (new MailMessage)
                ->subject('Restablecimiento de contraseña')
                ->view('emails.reset-password', [
                    'url' => $url,
                    'user' => $notifiable,
                    'expire' => $expireMinutes,
                ]);
        });
    }
}
