<?php

namespace App;

/**
 * Interface for YouTube video fetching services
 */
interface YoutubeInterface
{
    /**
     * Fetch video data for specified YouTube channels
     *
     * @param array $channelNames Array of channel usernames
     * @param array $channelsId Array of channel IDs
     * @return array Array of video data
     */
    public function fetchVideosData(array $channelNames, array $channelsId): array;

    /**
     * Scrapes metadata for a single YouTube video
     *
     * @param string $url The YouTube video URL
     * @param string|null $videoId Optional pre-extracted video ID (for API service)
     * @return bool True if successful, false otherwise
     */
    public function scrapVideoData(string $url, string $videoId): bool;
}
