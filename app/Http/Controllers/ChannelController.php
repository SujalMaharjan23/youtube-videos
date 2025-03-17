<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelController extends Controller
{
    /**
     * Add channels
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'channel_name' => 'required|string|unique:youtube_channels,channel_name',
                'channel_id' => 'required|string|unique:youtube_channels,channel_id',
                'username' => 'required|string|unique:youtube_channels,username',
                'description' => 'nullable|string',
                'channel_logo_url' => 'nullable|url',
                'tier_id' => 'required|exists:source_tiers,id',
            ]);

            DB::transaction(function () use ($validated) {
                $channel = Channel::create($validated);

                if ($channel) {
                    DB::table('youtube_tiers_pivot')->updateOrInsert([
                        'channel_id' => $channel->channel_id,
                        'tier_id' => $validated['tier_id'],
                    ]);
                }
            });

            return response()->json([
                'message' => 'Channel added successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all channels
     */
    public function index()
    {
        $channels = Channel::all();
        return response()->json([
            'data' => $channels
        ], 200);
    }

    /**
     * Get a specific channel by ID
     */
    public function show($id)
    {
        $channel = DB::table('youtube_channels')
            ->where('youtube_channels.id', $id)
            ->leftJoin('youtube_tiers_pivot', 'youtube_channels.channel_id', '=', 'youtube_tiers_pivot.channel_id')
            ->select('youtube_channels.*', 'youtube_tiers_pivot.tier_id')
            ->first();

        if ($channel == null) {
            return response()->json([
                'message' => 'Channel not found'
            ], 404);
        }

        return response()->json([
            'data' => $channel
        ], 200);
    }

    /**
     * Update a channel
     */
    public function update(Request $request, $id)
    {
        $channel = Channel::find($id);
        if (!$channel) {
            return response()->json([
                'message' => 'Channel not found'
            ], 404);
        }

        $validated = $request->validate([
            'channel_name' => 'required|string',
            'channel_id'   => 'required|string',
            'username'     => 'required|string',
            'description'  => 'sometimes|string',
            'channel_logo_url' => 'sometimes|url',
            'hidden' => 'sometimes|boolean',
            'tier_id' => 'required|exists:source_tiers,id'
        ]);

        DB::transaction(function() use ($channel, $validated) {
            $channel->update($validated);

            if($channel){
                DB::table('youtube_tiers_pivot')
                    ->where('channel_id', $channel->channel_id)
                    ->update(['tier_id' => $validated['tier_id']]);
            }
        });

        return response()->json([
            'message' => 'Channel updated successfully', 
        ], 200);
    }

    /**
     * Delete a channel
     */
    public function destroy($id)
    {
        $channel = Channel::find($id);
        if (!$channel) {
            return response()->json([
                'message' => 'Channel not found'
            ], 404);
        }

        DB::transaction(function () use ($channel) {
            $channel->delete();
        });

        return response()->json([
            'message' => 'Channel and related videos deleted successfully'
        ], 200);
    }

    public function getAllChannels(Request $request)
    {
        $sortBy = $request->get("sort_by", "desc");
        $sortField = $request->get("sort_field", "id");
        $limit = $request->get("limit", 10);

        $channels = DB::table('youtube_channels')
            ->leftJoin('youtube_tiers_pivot', 'youtube_channels.channel_id', '=', 'youtube_tiers_pivot.channel_id')
            ->select('youtube_channels.*', 'youtube_tiers_pivot.tier_id')
            ->orderBy($sortField, $sortBy)
            ->paginate($limit);

        return response()->json([
            "data" => $channels
        ], 200);
    }

}
