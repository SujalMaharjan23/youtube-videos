<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\Channel;
use App\YoutubeInterface;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Log;

class YoutubeYtDlpService implements YoutubeInterface
{
    public function fetchVideosData(array $channels_username, array $channels_id): array
    {
        $fileName = "YOUTUBE-YTDLP-SERVICE";
        $existingVideos = YoutubeVideo::whereIn('channel_id', $channels_id)
            ->pluck('video_id')
            ->toArray();

        $deletedVideos = YoutubeVideo::onlyTrashed()
            ->whereIn('channel_id', $channels_id)
            ->pluck('video_id')
            ->toArray();

        $storedVideos = [];

        foreach ($channels_username as $channelUsername) {
            $escapedChannel = escapeshellarg($channelUsername);
            $command = "yt-dlp -j --flat-playlist --playlist-end 10 'https://www.youtube.com/c/{$escapedChannel}'";
            $output = shell_exec($command);

            if (!$output) {
                Log::error($fileName, [
                    'message' => "yt-dlp failed for channel: {$channelUsername}"
                ]);
                continue;
            }

            $videoLines = explode("\n", trim($output));

            foreach ($videoLines as $line) {
                $videoData = json_decode($line, true);
                if (!isset($videoData['id'])) continue;

                $videoId = $videoData['id'];

                // Skip if video was soft-deleted
                if (in_array($videoId, $deletedVideos)) {
                    Log::info($fileName, [
                        "message" => "Skipped deleted video - video_id:$videoId"
                    ]);
                    continue;
                }

                $videoUrl = $videoData['url'];
                $isShort = str_contains($videoUrl, 'shorts') ? 1 : 0;
                $thumbnail = !empty($videoData['thumbnails']) ? end($videoData['thumbnails'])['url'] : null;
                $viewCount = $videoData['view_count'] ?? 0;
                $duration = $videoData['duration'] ?? null;

                // Fetch upload date from HTML
                $httpClient = new Client();
                $response = $httpClient->get($videoUrl);
                $html = (string) $response->getBody();
                preg_match('/"uploadDate":"([^"]+)"/', $html, $matches);
                $uploadDate = isset($matches[1]) ? $matches[1] : date('Y-m-d H:i:s');
                $uploadDate = $uploadDate ? Carbon::parse($uploadDate)->format('Y-m-d H:i:s') : null;

                // Fetch existing duration if missing
                if ($duration === null) {
                    $dbVideoData = YoutubeVideo::where('video_id', $videoId)->first();
                    $duration = $dbVideoData ? $dbVideoData->duration : null;
                }

                $channelId = Channel::where('username', $channelUsername)->value('channel_id');

                if (in_array($videoId, $existingVideos)) {
                    Log::info($fileName, [
                        'message' => "Updating video: $videoId"
                    ]);
                    YoutubeVideo::where('video_id', $videoId)->update([
                        'channel_id' => $channelId,
                        'title' => $videoData['title'] ?? null,
                        'description' => $videoData['description'] ?? null,
                        'video_url' => $videoUrl,
                        'thumbnail' => $thumbnail,
                        'view_count' => $viewCount,
                        'duration' => $duration,
                        'is_short' => $isShort,
                        'upload_date' => $uploadDate
                    ]);
                    $storedVideos[] = YoutubeVideo::where('video_id', $videoId)->first();
                } else {
                    Log::info($fileName, [
                        'message' => "Inserting video: $videoId"
                    ]);
                    $video = YoutubeVideo::create([
                        'video_id' => $videoId,
                        'channel_id' => $channelId,
                        'title' => $videoData['title'] ?? null,
                        'description' => $videoData['description'] ?? null,
                        'video_url' => $videoUrl,
                        'thumbnail' => $thumbnail,
                        'view_count' => $viewCount,
                        'duration' => $duration,
                        'is_short' => $isShort,
                        'upload_date' => $uploadDate
                    ]);

                    $storedVideos[] = $video;
                }
            }
        }

        return $storedVideos;
    }

    public function scrapVideoData(string $url, string $videoId): bool
    {
        $fileName = "YOUTUBE-YTDLP-SERVICE";
        // Run yt-dlp command to get video details in JSON format
        $command = "yt-dlp -J " . escapeshellarg($url);
        $output = shell_exec($command);

        if (!$output) return false;

        $videoDetails = json_decode($output, true);

        $channelId = $videoDetails['channel_id'] ?? null;
        if (!isset($videoDetails['id'], $videoDetails['channel_id'])) {
            Log::warning($fileName, [
                'message' => "Unable to get video data for url: $url using yt-dlp command."
            ]);
            return false;
        }

        $httpClient = new Client();
        $response = $httpClient->get($url);
        $html = (string) $response->getBody();
        preg_match('/"uploadDate":"([^"]+)"/', $html, $matches);
        $uploadDate = isset($matches[1]) ? $matches[1] : date('Y-m-d H:i:s');
        $uploadDate = $uploadDate ? Carbon::parse($uploadDate)->format('Y-m-d H:i:s') : null;

        $channelExist = Channel::where('channel_id', $channelId)->exists();
        if($channelExist == false){
            Log::info($fileName, [
                'message' => "Channel does not exist for channel_id: $channelId, so inserting in youtube_channels"
            ]);

            // / Fetch channel page for description and logo
            $channelUrl = "https://www.youtube.com/channel/{$channelId}";
            $response = $httpClient->get($channelUrl);
            $html = (string) $response->getBody();

            // Extract ytInitialData
            preg_match('/ytInitialData\s*=\s*({.+?});/s', $html, $dataMatches);
            $pageData = isset($dataMatches[1]) ? json_decode($dataMatches[1], true) : [];

            // Get channel description
            $channelDescription = isset($pageData['metadata']['channelMetadataRenderer']['description']) ? $pageData['metadata']['channelMetadataRenderer']['description'] : null;

            // Extract channel logo from og:image
            preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $logoMatches);
            $channelLogoUrl = isset($logoMatches[1]) ? $logoMatches[1] : null;

            // Fallback logo from yt-dlp if available
            if ($channelLogoUrl == null && isset($videoDetails['thumbnails'])) {
                // Look for a channel-related thumbnail (e.g., last one might be banner, first might be logo)
                $channelLogoUrl = $videoDetails['thumbnails'][0]['url'] ?? null;
            }
            
            $channel = [
                'channel_id' => $videoDetails['channel_id'] ?? null,
                'channel_name' => $videoDetails['channel'] ?? null,
                'username' => ltrim($videoDetails['uploader_id'], '@') ?? null,
                'description' => $channelDescription,
                'channel_logo_url' => $channelLogoUrl,
                'hidden' => $channelExist ? $channelExist->hidden : true
            ];
            $channelInserted = Channel::updateOrCreate(['channel_id' => $channel['channel_id']], $channel);
        }

        $video = [
            'channel_id' => isset($channel['channel_id']) ? $channel['channel_id'] : $channelId,
            'video_id' => $videoDetails['id'],
            'video_url' => $url,
            'title' => $videoDetails['title'] ?? null,
            'description' => $videoDetails['description'] ?? null,
            'thumbnail' => $videoDetails['thumbnail'] ?? null,
            'upload_date' => $uploadDate,
            'view_count' => $videoDetails['view_count'] ?? null,
            'like_count' => $videoDetails['like_count'] ?? null,
            'duration' => isset($videoDetails['duration']) ? $videoDetails['duration'] : null,
            'is_short' => str_contains($url, "short") ? 1 : 0
        ];

        $videoInserted = YoutubeVideo::updateOrCreate(['video_id' => $video['video_id']], $video);

        return true;
    }

}
