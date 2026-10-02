<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through Firebase Cloud Messaging (HTTP v1 API), authenticating
 * with the Firebase project's service account key (config('services.fcm.credentials')).
 * Without the key file nothing is sent and nothing fails: push is simply off.
 */
class PushNotifier
{
    /** Sends to every phone the user is signed in on. Returns how many accepted it. */
    public static function toUser(int $userId, string $title, string $body, array $data = []): int
    {
        $sent = 0;
        foreach (DeviceToken::where('user_id', $userId)->pluck('token') as $token) {
            if (self::send($token, $title, $body, $data)) {
                $sent++;
            }
        }
        return $sent;
    }

    public static function enabled(): bool
    {
        return self::credentials() !== null;
    }

    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (! $path || ! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        return isset($json['client_email'], $json['private_key'], $json['project_id']) ? $json : null;
    }

    private static function send(string $token, string $title, string $body, array $data): bool
    {
        $creds = self::credentials();
        if (! $creds) {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->withToken(self::accessToken($creds))
                ->post("https://fcm.googleapis.com/v1/projects/{$creds['project_id']}/messages:send", [
                    'message' => [
                        'token'        => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM data values must be strings.
                        'data'         => array_map('strval', $data + ['title' => $title, 'body' => $body]),
                        'android'      => [
                            'priority'     => 'high',
                            'notification' => ['channel_id' => 'laven_alerts', 'sound' => 'default'],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Push send failed: ' . $e->getMessage());
            return false;
        }

        if ($response->successful()) {
            return true;
        }

        // The app was uninstalled or the token rotated: forget it.
        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        if (in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $response->status() === 404) {
            DeviceToken::where('token', $token)->delete();
        } else {
            Log::warning('Push rejected', ['status' => $response->status(), 'body' => $response->body()]);
        }
        return false;
    }

    /** OAuth2 access token for FCM, from a JWT signed with the service account key; cached ~50 min. */
    private static function accessToken(array $creds): string
    {
        return Cache::remember('fcm_access_token_' . md5($creds['client_email']), 3000, function () use ($creds) {
            $now     = time();
            $encode  = fn ($v) => rtrim(strtr(base64_encode(is_string($v) ? $v : json_encode($v)), '+/', '-_'), '=');
            $header  = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $claims  = $encode([
                'iss'   => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]);
            openssl_sign("$header.$claims", $signature, $creds['private_key'], 'SHA256');
            $jwt = "$header.$claims." . $encode($signature);

            return Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ])->throw()->json('access_token');
        });
    }
}
