<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\JobEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    /* ── Authentication ─────────────────────────── */

    public function test_login_returns_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'admin@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_me_endpoint_requires_auth(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_endpoint_returns_user(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
             ->getJson('/api/me')
             ->assertStatus(200)
             ->assertJsonFragment(['email' => 'admin@test.com']);
    }

    /* ── Lead Lifecycle ─────────────────────────── */

    public function test_create_lead_returns_201(): void
    {
        $response = $this->postJson('/api/leads', [
            'name'  => 'John Test',
            'email' => 'john@test.com',
            'phone' => '07700900001',
        ]);
        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'John Test']);
    }

    public function test_lead_creates_associated_job_event(): void
    {
        $this->postJson('/api/leads', [
            'name'  => 'Jane Test',
            'email' => 'jane@test.com',
            'phone' => '07700900002',
        ]);

        $lead = Lead::where('email', 'jane@test.com')->first();
        $this->assertNotNull($lead);

        $job = JobEvent::where('lead_id', $lead->id)->where('type', 'Job')->first();
        $this->assertNotNull($job, 'A JobEvent should be auto-created when a lead is stored.');
    }

    public function test_lead_index_returns_leads(): void
    {
        Lead::create([
            'id' => 'L-TEST-1',
            'name' => 'Index Test',
            'email' => 'idx@test.com',
            'status' => 'draft',
            'stage' => 'New',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
             ->getJson('/api/leads')
             ->assertStatus(200)
             ->assertJsonFragment(['name' => 'Index Test']);
    }

    public function test_update_lead_status(): void
    {
        $lead = Lead::create([
            'id' => 'L-TEST-2',
            'name' => 'Status Test',
            'email' => 'status@test.com',
            'status' => 'draft',
            'stage' => 'New',
        ]);

        $this->patchJson("/api/leads/{$lead->id}/status", ['status' => 'booked'])
             ->assertStatus(200);

        $this->assertEquals('booked', $lead->fresh()->status);
    }

    /* ── Jobs ────────────────────────────────────── */

    public function test_jobs_index_does_not_delete_data(): void
    {
        $lead = Lead::create([
            'id' => 'L-TEST-3',
            'name' => 'Job Test',
            'email' => 'job@test.com',
            'status' => 'draft',  // Not a valid job status
            'stage' => 'New',
        ]);

        $job = JobEvent::create([
            'id' => 'J-TEST-1',
            'lead_id' => $lead->id,
            'type' => 'Job',
            'title' => 'Test Job',
            'event_date' => now(),
            'status' => 'booked',
        ]);

        // Fetching jobs index should NOT delete the job
        $this->getJson('/api/jobs');

        $this->assertNotNull(
            JobEvent::find('J-TEST-1'),
            'Job listing should NOT destructively delete job records.'
        );
    }

    public function test_jobs_index_excludes_non_booked_leads(): void
    {
        $lead = Lead::create([
            'id' => 'L-TEST-4',
            'name' => 'Draft Lead',
            'email' => 'draft@test.com',
            'status' => 'draft',
            'stage' => 'New',
        ]);

        JobEvent::create([
            'id' => 'J-TEST-2',
            'lead_id' => $lead->id,
            'type' => 'Job',
            'title' => 'Draft Job',
            'event_date' => now(),
            'status' => 'booked',
        ]);

        $response = $this->getJson('/api/jobs');
        $response->assertStatus(200);

        // The job for a draft lead should be filtered out of the response
        $jobs = collect($response->json('jobs'));
        $this->assertFalse($jobs->contains('id', 'J-TEST-2'));
    }

    /* ── Dashboard ──────────────────────────────── */

    public function test_dashboard_stats_returns_kpis(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
             ->getJson('/api/dashboard/stats')
             ->assertStatus(200)
             ->assertJsonStructure(['kpis', 'leads', 'jobEvents']);
    }

    /* ── Chat ────────────────────────────────────── */

    public function test_chat_members_returns_users(): void
    {
        User::factory()->create(['name' => 'Staff User', 'role' => 'staff']);

        $response = $this->getJson('/api/chat/members');
        $response->assertStatus(200);

        $members = $response->json();
        $this->assertNotEmpty($members);
    }

    public function test_chat_send_message(): void
    {
        $receiver = User::factory()->create(['name' => 'Receiver', 'role' => 'staff']);

        $response = $this->postJson('/api/chat/messages', [
            'sender_email' => $this->admin->email,
            'receiver_id'  => $receiver->id,
            'content'      => 'Hello from test!',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['content' => 'Hello from test!']);
    }
}
