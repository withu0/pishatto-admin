<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class LineAuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Socialite to avoid actual LINE API calls during testing
        $this->mockSocialite();
    }

    protected function mockSocialite()
    {
        $mockUser = Mockery::mock(SocialiteUser::class);
        $mockUser->shouldReceive('getId')->andReturn('line_123456');
        $mockUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $mockUser->shouldReceive('getName')->andReturn('Test User');
        $mockUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://line.me/oauth/authorize'));
        Socialite::shouldReceive('driver->user')->andReturn($mockUser);
    }

    /** @test */
    public function it_can_redirect_to_line_oauth()
    {
        $response = $this->get('/line/redirect?user_type=guest');

        $response->assertStatus(302);
        $this->assertEquals('line_123456', session('line_user_type'));
    }

    /** @test */
    public function it_can_handle_line_callback_for_existing_guest()
    {
        // Create a guest with LINE ID
        $guest = Guest::factory()->create([
            'line_id' => 'line_123456',
            'email' => 'test@example.com',
            'nickname' => 'Test User'
        ]);

        $response = $this->get('/line/callback?code=test_code');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_type' => 'guest',
            'message' => 'Guest logged in successfully'
        ]);
    }

    /** @test */
    public function it_can_handle_line_callback_for_existing_cast()
    {
        // Create a cast with LINE ID
        $cast = Cast::factory()->create([
            'line_id' => 'line_123456',
            'email' => 'test@example.com',
            'name' => 'Test Cast'
        ]);

        $response = $this->get('/line/callback?code=test_code');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_type' => 'cast',
            'message' => 'Cast logged in successfully'
        ]);
    }

    /** @test */
    public function it_can_handle_line_callback_for_new_user()
    {
        $response = $this->get('/line/callback?code=test_code');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_type' => 'new',
            'line_data' => [
                'line_id' => 'line_123456',
                'line_email' => 'test@example.com',
                'line_name' => 'Test User',
                'line_avatar' => 'https://example.com/avatar.jpg'
            ]
        ]);
    }

    /** @test */
    public function it_can_register_new_guest_with_line_data()
    {
        $guestData = [
            'user_type' => 'guest',
            'line_id' => 'line_123456',
            'line_email' => 'test@example.com',
            'line_name' => 'Test User',
            'line_avatar' => 'https://example.com/avatar.jpg',
            'additional_data' => [
                'nickname' => 'Test Guest',
                'phone' => '1234567890',
                'location' => 'Tokyo'
            ]
        ];

        $response = $this->post('/line/register', $guestData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_type' => 'guest',
            'message' => 'Guest registered and logged in successfully'
        ]);

        $this->assertDatabaseHas('guests', [
            'line_id' => 'line_123456',
            'email' => 'test@example.com',
            'nickname' => 'Test User'
        ]);
    }

    /** @test */
    public function it_can_register_new_cast_with_line_data()
    {
        $castData = [
            'user_type' => 'cast',
            'line_id' => 'line_123456',
            'line_email' => 'test@example.com',
            'line_name' => 'Test Cast',
            'line_avatar' => 'https://example.com/avatar.jpg',
            'additional_data' => [
                'name' => 'Test Cast',
                'phone' => '1234567890',
                'location' => 'Tokyo'
            ]
        ];

        $response = $this->post('/line/register', $castData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'user_type' => 'cast',
            'message' => 'Cast registered and logged in successfully'
        ]);

        $this->assertDatabaseHas('casts', [
            'line_id' => 'line_123456',
            'email' => 'test@example.com',
            'name' => 'Test Cast'
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_line_id_registration()
    {
        // Create existing user with LINE ID
        Guest::factory()->create(['line_id' => 'line_123456']);

        $guestData = [
            'user_type' => 'guest',
            'line_id' => 'line_123456',
            'line_email' => 'new@example.com',
            'line_name' => 'New User',
            'additional_data' => ['nickname' => 'New Guest']
        ];

        $response = $this->post('/line/register', $guestData);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Line account is already linked to another user'
        ]);
    }

    /** @test */
    public function it_can_link_existing_account_with_line()
    {
        $guest = Guest::factory()->create(['line_id' => null]);

        $linkData = [
            'user_type' => 'guest',
            'user_id' => $guest->id,
            'line_id' => 'line_123456'
        ];

        $response = $this->post('/line/link-account', $linkData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Account linked successfully'
        ]);

        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'line_id' => 'line_123456'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}




