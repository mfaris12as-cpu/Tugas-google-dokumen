<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            // Update last active time for guest users (throttled to once every 5 minutes to avoid DB query spam)
            if (str_ends_with($user->email, '@collabify.local')) {
                if (! $user->updated_at || $user->updated_at->lt(now()->subMinutes(5))) {
                    $user->touch();
                }
            }
        }

        // Clean up expired guest accounts (inactive for more than 24 hours) with a 5% chance per request
        if (rand(1, 100) <= 5) {
            User::where('email', 'like', '%@collabify.local')
                ->where('updated_at', '<', now()->subHours(24))
                ->delete();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'localIp' => $this->getLocalIp(),
        ];
    }

    /**
     * Dynamically detect the host local IP address (WiFi / LAN).
     */
    private function getLocalIp(): string
    {
        $ip = '127.0.0.1';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('ipconfig', $output);
            if (is_array($output)) {
                foreach ($output as $line) {
                    if (preg_match('/IPv4 Address[\. ]*: ([\d\.]+)/', $line, $match)) {
                        $currentIp = $match[1];
                        if ($currentIp !== '127.0.0.1') {
                            if (str_starts_with($currentIp, '192.168.') || str_starts_with($currentIp, '10.')) {
                                return $currentIp;
                            }
                            $ip = $currentIp;
                        }
                    }
                }
            }
        } else {
            @exec('hostname -I', $output);
            if (! empty($output) && is_array($output)) {
                $ips = explode(' ', trim($output[0]));
                if (isset($ips[0]) && filter_var($ips[0], FILTER_VALIDATE_IP)) {
                    return $ips[0];
                }
            }
        }

        return $ip;
    }
}
