<?php

namespace App\Http\Controllers;

use App\Models\YoutubeVideo;
use Illuminate\Http\Request;
use App\Services\YoutubeService;
use Illuminate\Support\Facades\Log;

class YoutubeVideoController extends Controller
{
    protected $youtubeService;

    public function __construct(YoutubeService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Fetch and store YouTube videos from a channel.
     */
    public function fetchAndStoreVideos(Request $request)
    {
        $validated = $request->validate([
            'channel_names' => 'array',
            'channel_names.*' => 'string'
        ]);

        $videos = $this->youtubeService->fetchVideosData($validated['channel_names'] ?? null);

        Log::info('YOUTUBEVIDEO-CONTROLLER',[
            'message' => count($videos) . ' Videos fetched successfully.'
        ]);

        return response()->json([
            'message' => count($videos) ? 'Videos fetched successfully.' : 'No new videos found.'
        ], 200);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(YoutubeVideo $youtubeVideo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(YoutubeVideo $youtubeVideo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, YoutubeVideo $youtubeVideo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(YoutubeVideo $youtubeVideo)
    {
        //
    }
}
