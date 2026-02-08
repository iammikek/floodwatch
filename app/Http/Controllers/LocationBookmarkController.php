<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationBookmarkRequest;
use App\Http\Requests\UpdateLocationBookmarkRequest;
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
     * Update a bookmark.
     */
    public function update(UpdateLocationBookmarkRequest $request, LocationBookmark $bookmark): RedirectResponse
    {
        $data = $request->validated();

        if ($request->resolvedLocation !== null) {
            $resolved = $request->resolvedLocation;
            $data['location'] = $request->resolvedDisplayName();
            $data['lat'] = $resolved['lat'];
            $data['lng'] = $resolved['lng'];
            $data['region'] = $resolved['region'] ?? null;
        }

        $bookmark->update($data);

        return redirect()->route('profile.edit')
            ->with('status', 'bookmark-updated');
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
