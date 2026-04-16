<?php

namespace App\Http\Middleware;

use App\Models\Settings\CompanySetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

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
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => fn () => $request->user()
                    ? (function () use ($request) {
                        $user = $request->user()->fresh(['roles.permissions']);
                        if (!$user) {
                            return null;
                        }

                        return [
                            ...$user->append('avatar_url')->makeHidden([
                                'area_id',
                                'created_at',
                                'updated_at',
                            ])->toArray(),
                            'theme_appearance' => $user->theme_appearance,
                            'theme_color' => $user->theme_color,
                        ];
                    })()
                    : null,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'company' => CompanySetting::first(),
        ];
    }
}
