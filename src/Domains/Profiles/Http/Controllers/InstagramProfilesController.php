<?php

namespace hexa_package_instagram\Domains\Profiles\Http\Controllers;

use hexa_package_instagram\Domains\Profiles\Models\InstagramProfile;
use hexa_package_instagram\Domains\Profiles\Services\InstagramProfileRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class InstagramProfilesController extends Controller
{
    public function index(Request $request, InstagramProfileRegistry $registry): View
    {
        $profiles = $registry->all();
        $selected = $request->query('id');
        $profile = $selected !== null
            ? collect($profiles)->firstWhere('id', (int) $selected)
            : ($profiles[0] ?? null);
        abort_if($selected !== null && $profile === null, 404);

        return view('instagram::profiles.index', ['profiles' => $profiles, 'profile' => $profile, 'sites' => $registry->sites()]);
    }

    public function store(Request $request, InstagramProfileRegistry $registry): RedirectResponse
    {
        $values = $request->validate([
            'slug' => ['required', 'string', 'max:80'],
            'site' => ['required', 'string', 'max:161'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        [$source, $key] = array_pad(explode(':', $values['site'], 2), 2, '');
        $profile = $registry->save(['slug' => $values['slug'], 'source' => $source, 'source_key' => $key, 'notes' => $values['notes'] ?? null]);

        return redirect()->route('instagram.profiles.index', ['id' => $profile->id])->with('status', 'Profile saved.');
    }

    public function refresh(InstagramProfile $profile, InstagramProfileRegistry $registry): RedirectResponse
    {
        try {
            $registry->refresh($profile);

            return redirect()->route('instagram.profiles.index', ['id' => $profile->id])->with('status', 'Locations and hosts refreshed from the website.');
        } catch (\Throwable $error) {
            report($error);

            return redirect()->route('instagram.profiles.index', ['id' => $profile->id])
                ->withErrors(['catalog' => 'The website refresh failed. The previous successful list has been preserved.']);
        }
    }
}
