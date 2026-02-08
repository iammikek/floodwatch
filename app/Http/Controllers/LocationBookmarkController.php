<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationBookmarkRequest;
use App\Models\LocationBookmark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LocationBookmarkController extends Controller
{
    /**
     * Store a new bookmark.
     */
    public function store(StoreLocationBookmarkRequest $request): RedirectResponse
    {
        $resolved = $request->resolvedLocation;

        Auth::user()->locationBookmarks()->create([
            'label' => $request->validated('label'),
            'location' => $request->resolvedDisplayName(),
            'lat' => $resolved['lat'],
            'lng' => $resolved['lng'],
            'region' => $resolved['region'] ?? null,
            'is_default' => Auth::user()->locationBookmarks()->count() === 0,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'bookmark-created');
    }

    /**
     * Set a bookmark as the user's default.
     */
    public function setDefault(LocationBookmark $bookmark): RedirectResponse
    {
        if ($bookmark->user_id !== Auth::id()) {
            abort(403);
        }

        LocationBookmark::query()
            ->where('user_id', Auth::id())
            ->update(['is_default' => false]);

        $bookmark->update(['is_default' => true]);

        return redirect()->route('profile.edit')
            ->with('status', 'bookmark-default-set');
    }

    /**
     * Delete a bookmark.
     */
    public function destroy(LocationBookmark $bookmark): RedirectResponse
    {
        if ($bookmark->user_id !== Auth::id()) {
            abort(403);
        }

        $bookmark->delete();

        return redirect()->route('profile.edit')
            ->with('status', 'bookmark-deleted');
    }
}
