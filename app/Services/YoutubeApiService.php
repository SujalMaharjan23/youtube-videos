<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Channel;
use App\YoutubeInterface;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class YoutubeApiService implements YoutubeInterface
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('YOUTUBE_API_KEY');
    }

    public function fetchVideosData(array $channels_name, array $channels_id): array
    {
        $fileName = "YOUTUBE-API-SERVICE";
        if (empty($this->apiKey)) {
            Log::error($fileName, [
                'message' => 'YOUTUBE_API_KEY key is missing.'
            ]);
            return ['storedVideos' => []];
        }

        $existingVideos = YoutubeVideo::whereIn('channel_id', $channels_id)
            ->pluck('video_id')
            ->toArray();

        $deletedVideos = YoutubeVideo::onlyTrashed()
            ->whereIn('channel_id', $channels_id)
            ->pluck('video_id')
            ->toArray();

        $storedVideos = [];
        $errorChannels = [];

        foreach ($channels_id as $channelId) {
            // Fetch latest videos from YouTube API
            $videosResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'channelId' => $channelId,
                'type' => 'video',
                'maxResults' => 20,
                'order' => 'date',
                'key' => $this->apiKey
            ]);

            if (!$videosResponse->successful()) {
                Log::warning($fileName, [
                    "message" => "Videos not found for channel_id: $channelId"
                ]);
                $errorChannels[] = $channelId;
                continue;
            }

            $videos = $videosResponse->json()['items'] ?? [];

            if (empty($videos)) {
                Log::info($fileName, [
                    "message" => "Empty videos data for channel_id: $channelId"
                ]);
                $errorChannels[] = $channelId;
                continue;
            }

            // Fetch detailed statistics for all video IDs
            $videoIds = collect($videos)->pluck('id.videoId')->implode(',');
            $videoDetailsResponse = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'statistics,contentDetails',
                'id' => $videoIds,
                'key' => $this->apiKey
            ]);

            if (!$videoDetailsResponse->successful()) {
                Log::warning($fileName, [
                    "message" => "Empty videos data for video_id: $videoIds, channel_id: $channelId"
                ]);
                continue;
            }

            $videoDetailsData = collect($videoDetailsResponse->json()['items'] ?? [])->keyBy('id');

            foreach ($videos as $video) {
                $videoId = $video['id']['videoId'];
                
                // Skip soft-deleted videos
                if (in_array($videoId, $deletedVideos)) {
                    Log::info($fileName, [
                        "message" => "Skipped deleted video - video_id:$videoId"
                    ]);
                    continue;
                }

                $details = $videoDetailsData[$videoId] ?? null;

                $title = $video['snippet']['title'];
                $description = $video['snippet']['description'];
                $thumbnail = $video['snippet']['thumbnails']['medium']['url'] ??
                             $video['snippet']['thumbnails']['default']['url'] ??
                             $video['snippet']['thumbnails']['high']['url'] ?? null;
                $uploadDate = isset($video['snippet']['publishedAt'])
                    ? Carbon::parse($video['snippet']['publishedAt'])->format('Y-m-d H:i:s')
                    : date('Y-m-d H:i:s');
                $viewCount = $details['statistics']['viewCount'] ?? null;
                $likeCount = $details['statistics']['likeCount'] ?? null;
                $duration = $details['contentDetails']['duration'] ?? null;
                $durationInSeconds = $this->formatDuration($duration);
                $isShort = $durationInSeconds <= 180;
                $videoUrl = $isShort ? "https://www.youtube.com/shorts/{$videoId}" : "https://www.youtube.com/watch?v={$videoId}";

                if (in_array($videoId, $existingVideos)) {
                    Log::info($fileName, [
                        'message' => "Updating video: $videoId"
                    ]);
                    // Update only active records
                    YoutubeVideo::where('video_id', $videoId)->update([
                        'video_url' => $videoUrl,
                        'title' => $title,
                        'description' => $description,
                        'thumbnail' => $thumbnail,
                        'upload_date' => $uploadDate,
                        'channel_id' => $channelId,
                        'view_count' => $viewCount,
                        'like_count' => $likeCount,
                        'duration' => $durationInSeconds,
                        'is_short' => $isShort
                    ]);

                    $storedVideos[] = YoutubeVideo::where('video_id', $videoId)->first();
                } else {
                    Log::info($fileName, [
                        'message' => "Inserting video: $videoId"
                    ]);
                    // Insert only if not soft-deleted before
                    $videoRecord = YoutubeVideo::create([
                        'video_id' => $videoId,
                        'video_url' => $videoUrl,
                        'title' => $title,
                        'description' => $description,
                        'thumbnail' => $thumbnail,
                        'upload_date' => $uploadDate,
                        'channel_id' => $channelId,
                        'view_count' => $viewCount,
                        'like_count' => $likeCount,
                        'duration' => $durationInSeconds,
                        'is_short' => $isShort
                    ]);

                    $storedVideos[] = $videoRecord;
                }
            }
        }
        return [
            'storedVideos' => $storedVideos,
            'errorChannels' => $errorChannels
        ];
    }

    private function formatDuration($duration)
    {
        // Handle the case where no duration is available
        if (!$duration) {
            return null;
        }

        // Create a DateInterval from the ISO 8601 duration string
        $interval = new \DateInterval($duration);

        // Use Carbon's method
        $carbonDate = Carbon::now()->add($interval);

        return $carbonDate->diffInSeconds();
        // return $carbonDate->diffForHumans(Carbon::now(), true);
    }

    public function scrapVideoData(string $url, string $video_id): bool
    {
        $fileName = "YOUTUBE-API-SERVICE";
        if (empty($this->apiKey)) {
            Log::error($fileName, [
                'message' => 'YOUTUBE_API_KEY key is missing.'
            ]);
            return false;
        }

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet, statistics,contentDetails',
            'id' => $video_id,
            'key' => $this->apiKey
        ]);

        $videoData = $videoResponse->json()['items'][0] ?? null;
        if ($videoData == null){
            Log::warning($fileName, [
                "message" => "Unable to get video data for url: $url using console api."
            ]);
            return false;
        }

        $snippet = $videoData['snippet'] ?? [];
        $statistics = $videoData['statistics'] ?? [];
        $contentDetails = $videoData['contentDetails'] ?? [];
        $channelId = $snippet['channelId'] ?? null;

        if ($channelId == null){
            Log::warning($fileName, [
                "message" => "Unable to get channel_id for url: $url using console api.",
                "video_data" => $videoData
            ]);
            return false;
        }

        $channelExist = Channel::where('channel_id', $channelId)->exists();

        if($channelExist == false){
            Log::info($fileName, [
                'message' => "Channel does not exist for channel_id: $channelId, so inserting in youtube_channels"
            ]);
            $channelResponse = Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'id' => $channelId,
                'key' => $this->apiKey
            ]);
    
            $channelData = $channelResponse->json()['items'][0]['snippet'] ?? null;
            if (!$channelData){
                Log::warning($fileName, [
                    'message' => "Channel data not found for url: $url using console api"
                ]);
                return false;
            }
    
            $channel = [
                'channel_id' => $channelId,
                'channel_name' => $channelData['title'],
                'username' => ltrim($channelData['customUrl'], '@'),
                'description' => $channelData['description'],
                'channel_logo_url' => $channelData['thumbnails']['high']['url'] ?? 
                                    $channelData['thumbnails']['medium']['url'] ?? 
                                    $channelData['thumbnails']['default']['url'] ?? null,
                'hidden' => true
            ];

            $channelInserted = Channel::updateOrCreate(['channel_id' => $channelId], $channel);
        }

        $video = [
            'channel_id' => $channelId,
            'video_id' => $video_id,
            'video_url' => $url,
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'thumbnail' => $snippet['thumbnails']['medium']['url'] ?? 
                            $snippet['thumbnails']['default']['url'] ?? 
                            $snippet['thumbnails']['high']['url'] ?? null,
            'upload_date' => isset($snippet['publishedAt'])
                ? Carbon::parse($snippet['publishedAt'])->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s'),
            'view_count' => $statistics['viewCount'] ?? null,
            'like_count' => $statistics['likeCount'] ?? null,
            'duration' => $this->formatDuration($contentDetails['duration']) ?? null,
            'is_short' => str_contains($url, "short") ? 1 : 0
        ];
        
        $videoInserted = YoutubeVideo::updateOrCreate(['video_id' => $video_id], $video);

        return true;
    }
}
