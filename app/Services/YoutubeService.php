<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\YoutubeVideo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\YoutubeInterface;

class YoutubeService
{
    protected $primaryFetcher;
    protected $fallbackFetcher;

    public function __construct(YoutubeInterface $apiService, YoutubeInterface $ytDlpService)
    {
        $this->primaryFetcher = $apiService;
        $this->fallbackFetcher = $ytDlpService;
    }

    public function fetchVideosData($channel_names): array
    {
        if ($channel_names == null) {
            Log::info("YOUTUBE-SERVICE", [
                'message' => "Fetching youtube videos for all active channels"
            ]);
            $channels = Channel::where('hidden', 0)
                ->pluck('channel_id', 'username')
                ->toArray();
        } else {
            Log::info("YOUTUBE-SERVICE", [
                'message' => "Fetching youtube videos for channels: " . implode(', ', $channel_names)
            ]);
            $channels = Channel::whereIn('channel_name', $channel_names)
                ->pluck('channel_id', 'username')
                ->toArray();
        }

        $channelsUsername = array_keys($channels);
        $channelsId = array_values($channels);
        $videos = [];

        Log::info("YOUTUBE-SERVICE", [
            'message' => "Fetch youtube videos using console API for channels: " . implode(', ', $channelsUsername)
        ]);
        $primaryResult = $this->primaryFetcher->fetchVideosData($channelsUsername, $channelsId);

        if (empty($primaryResult['storedVideos'])) {
            Log::warning("YOUTUBE-SERVICE", [
                'message' => "YouTube API failed. Fetching using yt-dlp for channels: " . implode(', ', $channelsUsername)
            ]);
            $videos = $this->fallbackFetcher->fetchVideosData($channelsUsername, $channelsId);
        } elseif (!empty($primaryResult['errorChannels'])) {
            Log::warning("YOUTUBE-SERVICE", [
                'message' => "YouTube API failed for channels: " . implode(', ', $primaryResult['errorChannels']) . ". Fetching using yt-dlp"
            ]);
            $errorChannels = array_intersect_key(array_flip($channels), array_flip($primaryResult['errorChannels']));
            $errorChannelsId = array_keys($errorChannels);
            $errorChannelsUsername = array_values($errorChannels);
            $fallbackVideos = $this->fallbackFetcher->fetchVideosData($errorChannelsUsername, $errorChannelsId);
            $videos = array_merge($primaryResult['storedVideos'], $fallbackVideos);
        } else {
            $videos = $primaryResult['storedVideos'];
        }

        return $videos;
    }

    public function scrapVideoData(string $url)
    {
        $videoId = $this->extractVideoId($url);
        if($videoId == null){
            return response()->json([
                'message' => 'Failed to get Video Id'
            ], 400);
        }

        Log::info("YOUTUBE-SERVICE", [
            'message' => "Scrap youtube video using console api for url: $url"
        ]);
        $videoData = $this->primaryFetcher->scrapVideoData($url, $videoId);
        if ($videoData == false) {
            Log::warning("YOUTUBE-SERVICE",[
                'message' => "YouTube API failed for $url. Trying to scrap youtube video using yt-dlp"
            ]);
            $videoData = $this->fallbackFetcher->scrapVideoData($url, $videoId);
        }

        return $videoData;
    }
    
    private function extractVideoId($url) {
        $parsedUrl = parse_url($url);
        // If URL is from youtu.be (shortened URL)
        if ($parsedUrl['host'] === 'youtu.be') {
            return trim($parsedUrl['path'], '/');
        }
    
        // If URL is from youtube.com
        if ($parsedUrl['host'] === 'www.youtube.com' || $parsedUrl['host'] === 'youtube.com') {
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                if (!empty($queryParams['v'])) {
                    return $queryParams['v']; // Regular YouTube video
                }
            }
    
            // Handle YouTube Shorts URLs
            if (isset($parsedUrl['path']) && strpos($parsedUrl['path'], '/shorts/') === 0) {
                return basename($parsedUrl['path']); // Extract VIDEO_ID from /shorts/VIDEO_ID
            }
        }
    
        return null; // Return null if no valid ID found
    }

    public function getAllVideos($request = null)
    {
        if($request == null){
            $videos = YoutubeVideo::with('channel')
                ->where('is_short', 0)
                ->orderBy('upload_date', 'desc')
                ->paginate(10);
        } else{
            // Retrieve query parameters with default values
            $sortBy = $request->get("sort_by", "desc");
            $sortField = $request->get("sort_field", "upload_date"); // Default sorting by upload_date
            $limit = $request->get("limit", 10); // Default limit 10
            $filters = $request->get("filter", []); // Filters

            // Query builder
            $query = YoutubeVideo::query();

            foreach ($filters as $field => $value) {
                if (!empty($value)) {
                    if($field === "title"){
                        $query->where($field, "LIKE", "%$value%");
                    }else{
                        $query->where($field, $value);
                    }
                }
            }

            $videos = $query->with('channel')->orderBy($sortField, $sortBy)->paginate($limit);
        }

        return $videos;
    }

    public function getChannelVideos($channel_id, $suggest = false, $video_id = null)
    {
        $channel = Channel::where('channel_id', $channel_id)->exists();
        if($channel == false && $suggest == false){
            return response()->json([
                'message' => 'Channel not found.'
            ], 404);
        }

        $videos = YoutubeVideo::with('channel')
            ->where([
                'channel_id' => $channel_id,
                'is_short' => false
            ])
            ->where('video_id', '!=', $video_id)
            ->orderBy('upload_date', 'desc')
            ->paginate(10);

        if($videos->isEmpty() && $suggest == true){
            return null;
        }
        
        return $videos;
    }

    public function getAllShorts()
    {
        $shorts = YoutubeVideo::where('is_short', true)
            ->with('channel')
            ->orderBy('upload_date', 'desc')
            ->paginate(10);

        return $shorts;
    }

    public function getChannelShorts($channel_id)
    {
        $channel = Channel::where('channel_id', $channel_id)->exists();
        if($channel == false){
            return response()->json([
                'message' => 'Channel not found.'
            ], 404);
        }
        
        $shorts = YoutubeVideo::with('channel')
            ->where([
                'channel_id' => $channel_id,
                'is_short' => 1
            ])
            ->orderBy('upload_date', 'desc')
            ->paginate(10);
        
        return $shorts;
    }

    public function getSuggestedVideo($video_id)
    {
        $data = YoutubeVideo::where('video_id', $video_id)->firstOrFail();

        $suggestion = $this->getChannelVideos($data->channel_id, true, $video_id);
        if($suggestion == null){
            $suggestion = $this->getAllVideos();
        }
        return $suggestion;
    }

    public function incrementHitCount($video_id)
    {
        $multiplier = isset($env['MULTIPLY']) ? $env['NEWS_VIEWS_MULTIPLY_BY'] : 3;
        $update_query = "INSERT INTO `video_hits` ( id,video_uuid,count,multiplied_count,multiplier) VALUES(NULL,?,?,?,?) ON DUPLICATE KEY UPDATE count = count + 1 , multiplied_count = (count)*$multiplier, updated_at = NOW()";
        DB::insert($update_query, [$video_id,1,1 * $multiplier,$multiplier]);
    }

    public function getTrendingVideos()
    {
        $timestamp = Carbon::now()->subDays(3); // last three days

        $trendingVideos = YoutubeVideo::join('video_hits', 'youtube_videos.video_id', '=', 'video_hits.video_uuid')
            ->where('video_hits.updated_at', '>=', $timestamp) // Filter hits in last 3 days
            ->where('youtube_videos.is_short', '0') // Exclude shorts
            // ->orderByDesc('video_hits.count') // Sort by highest hits
            ->orderByDesc('youtube_videos.view_count') // Sort by highest view count
            ->with('channel') // Load channel details
            ->select('youtube_videos.*', 'video_hits.count')
            ->take(10)
            ->get();

        return $trendingVideos;
    }

    public function deleteVideo($video_id)
    {
        $video = YoutubeVideo::where('id', $video_id)->first();

        if($video == null){
            return false;
        }

        $video->delete();
        return true;
    }

    public function getVideoDetail($video_id)
    {
        $video = YoutubeVideo::with('channel')->where('video_id', $video_id)->first();

        if($video == null){
            return null;
        }

        return $video;
    }
}
