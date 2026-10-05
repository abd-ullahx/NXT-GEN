<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\CallSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ZegoCallWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $surveyor;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'  => 'admin',
            'name'  => 'Admin User',
            'email' => 'admin@test.com',
        ]);

        $this->surveyor = User::factory()->create([
            'role'  => 'surveyor',
            'name'  => 'Field Surveyor',
            'email' => 'surveyor@test.com',
        ]);

        $this->adminToken = $this->admin->createToken('admin-test')->plainTextToken;
    }

    /**
     * Test Ad-hoc / Virtual Survey video room creation with Zego credentials.
     */
    public function test_authenticated_user_can_create_zego_video_call_room(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/video-calls/create-room', []);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'roomCode',
                'roomUrl',
                'zegoAppId',
                'zegoAppSign',
                'zegoServerSecret',
                'created_at',
            ]);

        $this->assertEquals((int) config('services.zego.app_id'), $response->json('zegoAppId'));
        $this->assertEquals(config('services.zego.app_sign'), $response->json('zegoAppSign'));
        $this->assertEquals(config('services.zego.server_secret'), $response->json('zegoServerSecret'));
        $this->assertNotEmpty($response->json('roomCode'));
    }

    /**
     * Test full Team Chat Voice/Video Call lifecycle: start -> incoming poll -> accept -> end.
     */
    public function test_chat_call_full_lifecycle(): void
    {
        // 1. Start call from Admin to Surveyor
        $startRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/chat/calls/start', [
                'recipient_id' => $this->surveyor->id,
                'is_video'     => true,
            ]);

        $startRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'call' => [
                    'status'    => 'ringing',
                    'isVideo'   => true,
                    'callType'  => 'video',
                    'callerId'  => (string) $this->admin->id,
                    'recipientId' => (string) $this->surveyor->id,
                    'zegoAppId' => (int) config('services.zego.app_id'),
                    'zegoAppSign' => config('services.zego.app_sign'),
                ],
            ]);

        $callId = $startRes->json('call.id');
        $roomId = $startRes->json('call.roomId');
        $this->assertNotEmpty($roomId);

        // 2. Surveyor polls incoming calls
        $incomingRes = $this->getJson("/api/chat/calls/incoming?userId={$this->surveyor->id}");
        $incomingRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'call' => [
                    'id'     => $callId,
                    'roomId' => $roomId,
                    'status' => 'ringing',
                ],
            ]);

        // 3. Surveyor accepts the call
        $acceptRes = $this->postJson("/api/chat/calls/{$callId}/action", [
            'action'  => 'accept',
            'user_id' => $this->surveyor->id,
        ]);

        $acceptRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'call' => [
                    'id'     => $callId,
                    'status' => 'in_progress',
                ],
            ]);

        // 4. Admin ends the call
        $endRes = $this->postJson("/api/chat/calls/{$callId}/action", [
            'action'  => 'end',
            'user_id' => $this->admin->id,
        ]);

        $endRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'call' => [
                    'id'        => $callId,
                    'status'    => 'completed',
                    'endReason' => 'hangup',
                ],
            ]);
    }

    /**
     * Test Lead Virtual Survey Video Call initiation, polling, and status synchronization.
     */
    public function test_lead_video_call_workflow(): void
    {
        $lead = Lead::create([
            'id'     => 'L-TEST-ZEGO',
            'name'   => 'Test Customer',
            'email'  => 'customer@test.com',
            'phone'         => '07123456789',
            'source'        => 'Website Form',
            'move_type'     => '3-Bed House',
            'from_location' => 'London',
            'to_location'   => 'Manchester',
            'move_date'     => '2026-10-01',
            'status'        => 'new',
            'stage'         => 'Survey Booked',
        ]);

        // 1. Mobile surveyor / app initiates lead video call
        $startRes = $this->postJson("/api/leads/{$lead->id}/video-call/start", [
            'callerName' => 'Senior Surveyor',
            'callerRole' => 'surveyor',
            'targetRole' => 'admin',
            'isVideo'    => true,
        ]);

        $startRes->assertStatus(200)
            ->assertJson([
                'success'   => true,
                'status'    => 'ringing',
                'leadId'    => $lead->id,
                'zegoAppId' => (int) config('services.zego.app_id'),
                'zegoAppSign' => config('services.zego.app_sign'),
            ]);

        $roomId = $startRes->json('roomId');
        $this->assertNotEmpty($roomId);

        // 2. Customer or Admin polls call status
        $statusRes = $this->getJson("/api/leads/{$lead->id}/video-call/status");
        $statusRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'ringing',
                'roomId'  => $roomId,
                'isVideo' => true,
                'zegoAppId' => (int) config('services.zego.app_id'),
            ]);

        // 3. Admin answers/accepts call via status sync
        $syncRes = $this->postJson("/api/leads/{$lead->id}/video-call/status", [
            'status' => 'in_progress',
        ]);

        $syncRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'in_progress',
            ]);

        // 4. End call
        $endRes = $this->postJson("/api/leads/{$lead->id}/video-call/status", [
            'status' => 'ended',
        ]);

        $endRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'ended',
            ]);
    }
}
