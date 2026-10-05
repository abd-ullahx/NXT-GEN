<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CallPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $driver;
    private Call $call;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'  => 'admin',
            'email' => 'admin_policy@test.com',
        ]);

        $this->staff = User::factory()->create([
            'role'  => 'staff',
            'email' => 'staff_policy@test.com',
        ]);

        $this->driver = User::factory()->create([
            'role'        => 'driver',
            'email'       => 'driver_policy@test.com',
            'permissions' => ['jobs' => true],
        ]);

        $this->call = Call::create([
            'provider_call_sid' => 'policy_call_sid_1',
            'recording_path'    => 'recordings/2026/09/call_1.webm',
            'transcript_status' => 'done',
            'transcript'        => 'Confidential client discussion.',
        ]);
    }

    public function test_admin_and_staff_can_view_calls_and_transcripts(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $this->call));
        $this->assertTrue(Gate::forUser($this->admin)->allows('viewRecording', $this->call));
        $this->assertTrue(Gate::forUser($this->admin)->allows('retryTranscription', $this->call));
        $this->assertTrue(Gate::forUser($this->admin)->allows('delete', $this->call));

        $this->assertTrue(Gate::forUser($this->staff)->allows('view', $this->call));
        $this->assertTrue(Gate::forUser($this->staff)->allows('viewRecording', $this->call));
        $this->assertTrue(Gate::forUser($this->staff)->allows('retryTranscription', $this->call));
        $this->assertFalse(Gate::forUser($this->staff)->allows('delete', $this->call));
    }

    public function test_driver_cannot_view_calls_or_transcripts(): void
    {
        $this->assertFalse(Gate::forUser($this->driver)->allows('view', $this->call));
        $this->assertFalse(Gate::forUser($this->driver)->allows('viewRecording', $this->call));
        $this->assertFalse(Gate::forUser($this->driver)->allows('retryTranscription', $this->call));
        $this->assertFalse(Gate::forUser($this->driver)->allows('delete', $this->call));
    }
}
