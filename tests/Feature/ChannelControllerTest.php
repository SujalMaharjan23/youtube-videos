<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Channel;
use App\Models\SourceTier;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChannelControllerTest extends TestCase
{
    use RefreshDatabase; // Reset database for each test

    protected function setUp(): void
    {
        parent::setUp();
        // Run migrations for testing
        $this->artisan('migrate');
    }

    public function test_admin_can_store_channel()
    {
        // Arrange: Create an admin and authenticate
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');
        // Create a source tier
        $tier = SourceTier::factory()->create();

        // Act: Send POST request with auth
        $response = $this->postJson('/api/admin/youtube/channels', [
            'channel_name' => 'Test Channel',
            'channel_id' => 'UC123456789',
            'username' => 'testchannel',
            'description' => 'Test description',
            'channel_logo_url' => 'https://example.com/logo.jpg',
            'tier_id' => $tier->id,
        ]);

        // Debugging: Dump the database contents
        // dump(DB::table('youtube_channels')->get()->toArray());
        // dump(DB::table('youtube_tiers_pivot')->get()->toArray());

        // Assert: Check response and database
        $response->assertStatus(201)
                 ->assertJson(['message' => 'Channel added successfully']);
        $this->assertDatabaseHas('youtube_channels', [
            'channel_id' => 'UC123456789',
            'channel_name' => 'Test Channel',
        ]);
        $this->assertDatabaseHas('youtube_tiers_pivot', [
            'channel_id' => 'UC123456789',
            'tier_id' => $tier->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_store_channel()
    {
        // Act: Send POST request without auth
        $response = $this->postJson('/api/admin/youtube/channels', [
            'channel_name' => 'Test Channel',
            'channel_id' => 'UC123456789',
            'username' => 'testchannel',
            'description' => 'Test description',
            'channel_logo_url' => 'https://example.com/logo.jpg',
            'tier_id' => 1,
        ]);

        // Assert: Check 401 response
        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_index_returns_all_channels_for_public()
    {
        // Arrange: Create admin and channels
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');
        // Channel::factory()->count(3)->create(['hidden' => 0]);
        Channel::factory()->count(3)->create();
        // Channel::factory()->create(['hidden' => 0]); // Visible channel
        // Channel::factory()->create(['hidden' => 1]); // Hidden channel
        // Channel::factory()->create(['hidden' => 1]); // Another hidden channel

        // Debugging: Dump the database contents
        // dump(DB::table('youtube_channels')->get()->toArray());
        // dump(DB::table('youtube_tiers_pivot')->get()->toArray());

        // Act: Send GET request
        $response = $this->getJson('/api/admin/youtube/channels');
        
        // dump($response->json());

        // Assert: Check response
        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_show_returns_channel_with_tier()
    {
        // Arrange: Create admin
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');
        // Arrange: Create channel and pivot entry
        $tier = SourceTier::factory()->create();
        $channel = Channel::factory()->create();
        DB::table('youtube_tiers_pivot')->insert([
            'channel_id' => $channel->channel_id,
            'tier_id' => $tier->id,
        ]);

        // Act: Send GET request
        $response = $this->getJson("/api/admin/youtube/channels/{$channel->id}");

        // dump($response->json());

        // Assert: Check response
        $response->assertStatus(200)
                 ->assertJsonFragment(['channel_id' => $channel->channel_id])
                 ->assertJsonFragment(['tier_id' => $tier->id]);
    }

    public function test_admin_can_update_channel()
    {
        // Arrange: Create admin, channel, and tier
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $originalTier = SourceTier::factory()->create();
        $newTier = SourceTier::factory()->create();
        $channel = Channel::factory()->create([
            'channel_name' => 'Original Channel',
            'channel_id' => 'UC123456789',
            'username' => 'originalchannel',
        ]);
        DB::table('youtube_tiers_pivot')->insert([
            'channel_id' => $channel->channel_id,
            'tier_id' => $originalTier->id,
        ]);

        // Act: Send PUT request
        $response = $this->putJson("/api/admin/youtube/channels/{$channel->id}", [
            'channel_name' => 'Updated Channel',
            'channel_id' => 'UC123456789',
            'username' => 'updatedchannel',
            'description' => 'Updated description',
            'channel_logo_url' => 'https://example.com/updated-logo.jpg',
            'tier_id' => $newTier->id,
        ]);

        // Debugging: Dump the database contents
        // dump(DB::table('youtube_channels')->get()->toArray());
        // dump(DB::table('youtube_tiers_pivot')->get()->toArray());

        // Assert: Check response and database
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Channel updated successfully']);
        $this->assertDatabaseHas('youtube_channels', [
            'id' => $channel->id,
            'channel_name' => 'Updated Channel',
            'username' => 'updatedchannel',
            'description' => 'Updated description',
            'channel_logo_url' => 'https://example.com/updated-logo.jpg',
        ]);
        $this->assertDatabaseHas('youtube_tiers_pivot', [
            'channel_id' => $channel->channel_id,
            'tier_id' => $newTier->id,
        ]);
    }

    public function test_admin_can_destroy_channel()
    {
        // Arrange: Create admin and channel
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $channel = Channel::factory()->create();

        // Debugging: Dump the database contents
        // dump(DB::table('youtube_channels')->get()->toArray());
        
        // Act: Send DELETE request
        $response = $this->deleteJson("/api/admin/youtube/channels/{$channel->id}");
        
        // Debugging: Dump the database contents
        // dump(DB::table('youtube_channels')->get()->toArray());
        
        // Assert: Check response and database
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Channel and related videos deleted successfully']);
        $this->assertDatabaseMissing('youtube_channels', ['id' => $channel->id]);
    }

    /**
     * A basic feature test example.
     */
    // public function test_example(): void
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }
}
