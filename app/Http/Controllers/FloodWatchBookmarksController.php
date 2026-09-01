<?php

namespace App\Http\Controllers;

use App\Models\LocationBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FloodWatchBookmarksController extends Controller
{
    /**
     * List location bookmarks for the signed-in user (cockpit sidebar).
     */
    public function __invoke(): JsonResponse
    {
        if (Auth::guest()) {
            return response()->json([
                'authenticated' => false,
                'items' => [],
            ]);
        }

        $items = Auth::user()->locationBookmarks()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (LocationBookmark $bookmark) => [
                'id' => $bookmark->id,
                'label' => $bookmark->label,
                'location' => $bookmark->location,
                'lat' => $bookmark->lat,
                'lng' => $bookmark->lng,
                'region' => $bookmark->region,
                'is_default' => $bookmark->is_default,
            ])
            ->values()
            ->all();

        return response()->json([
            'authenticated' => true,
            'items' => $items,
        ]);
    }
}
