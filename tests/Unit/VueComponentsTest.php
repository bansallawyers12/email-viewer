<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class VueComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_render_main_app_component()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Email Viewer');
        $response->assertSee('id="app"');
    }

    /** @test */
    public function it_includes_vue_components_in_layout()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that Vue.js is loaded
        $response->assertSee('vue');
        
        // Check that main app component is referenced
        $response->assertSee('App.vue');
    }

    /** @test */
    public function it_has_proper_csrf_token()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('csrf-token');
    }

    /** @test */
    public function it_includes_required_stylesheets()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check for Tailwind CSS
        $response->assertSee('tailwind');
        
        // Check for Font Awesome
        $response->assertSee('font-awesome');
    }

    /** @test */
    public function it_has_proper_viewport_meta_tag()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('viewport');
        $response->assertSee('width=device-width');
    }

    /** @test */
    public function it_includes_vite_assets()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('@vite');
    }

    /** @test */
    public function it_has_loading_state()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('animate-spin');
        $response->assertSee('Loading');
    }

    /** @test */
    public function it_has_proper_html_structure()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check for proper HTML5 structure
        $response->assertSee('<!DOCTYPE html>');
        $response->assertSee('<html');
        $response->assertSee('<head>');
        $response->assertSee('<body>');
    }

    /** @test */
    public function it_has_proper_language_attribute()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('lang="en"');
    }

    /** @test */
    public function it_includes_google_fonts()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('fonts.bunny.net');
    }

    /** @test */
    public function it_has_proper_meta_tags()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('charset="utf-8"');
        $response->assertSee('name="csrf-token"');
    }

    /** @test */
    public function it_has_robots_txt()
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_has_favicon()
    {
        $response = $this->get('/favicon.ico');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_has_proper_error_handling()
    {
        // Test 404 page
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_has_proper_asset_compilation()
    {
        // Check if Vite is properly configured
        $this->assertFileExists(public_path('build'));
        
        // Check if manifest exists
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $this->assertIsArray($manifest);
        }
    }

    /** @test */
    public function it_has_proper_environment_configuration()
    {
        $this->assertFileExists(base_path('.env'));
        $this->assertFileExists(base_path('.env.example'));
    }

    /** @test */
    public function it_has_proper_database_configuration()
    {
        $this->assertFileExists(config_path('database.php'));
        
        // Check if database connection is configured
        $this->assertArrayHasKey('default', config('database.connections'));
    }

    /** @test */
    public function it_has_proper_storage_configuration()
    {
        $this->assertFileExists(config_path('filesystems.php'));
        
        // Check if storage is properly configured
        $this->assertArrayHasKey('local', config('filesystems.disks'));
    }

    /** @test */
    public function it_has_proper_cache_configuration()
    {
        $this->assertFileExists(config_path('cache.php'));
        
        // Check if cache is properly configured
        $this->assertArrayHasKey('default', config('cache.stores'));
    }

    /** @test */
    public function it_has_proper_session_configuration()
    {
        $this->assertFileExists(config_path('session.php'));
        
        // Check if session is properly configured
        $this->assertArrayHasKey('driver', config('session'));
    }

    /** @test */
    public function it_has_proper_mail_configuration()
    {
        $this->assertFileExists(config_path('mail.php'));
        
        // Check if mail is properly configured
        $this->assertArrayHasKey('default', config('mail.mailers'));
    }

    /** @test */
    public function it_has_proper_queue_configuration()
    {
        $this->assertFileExists(config_path('queue.php'));
        
        // Check if queue is properly configured
        $this->assertArrayHasKey('default', config('queue.connections'));
    }

    /** @test */
    public function it_has_proper_logging_configuration()
    {
        $this->assertFileExists(config_path('logging.php'));
        
        // Check if logging is properly configured
        $this->assertArrayHasKey('default', config('logging.channels'));
    }

    /** @test */
    public function it_has_proper_auth_configuration()
    {
        $this->assertFileExists(config_path('auth.php'));
        
        // Check if auth is properly configured
        $this->assertArrayHasKey('defaults', config('auth'));
    }

    /** @test */
    public function it_has_proper_app_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if app is properly configured
        $this->assertArrayHasKey('name', config('app'));
        $this->assertArrayHasKey('env', config('app'));
        $this->assertArrayHasKey('debug', config('app'));
    }

    /** @test */
    public function it_has_proper_services_configuration()
    {
        $this->assertFileExists(config_path('services.php'));
        
        // Check if services are properly configured
        $this->assertIsArray(config('services'));
    }

    /** @test */
    public function it_has_proper_broadcasting_configuration()
    {
        $this->assertFileExists(config_path('broadcasting.php'));
        
        // Check if broadcasting is properly configured
        $this->assertArrayHasKey('default', config('broadcasting.connections'));
    }

    /** @test */
    public function it_has_proper_hashing_configuration()
    {
        $this->assertFileExists(config_path('hashing.php'));
        
        // Check if hashing is properly configured
        $this->assertArrayHasKey('driver', config('hashing'));
    }

    /** @test */
    public function it_has_proper_cors_configuration()
    {
        $this->assertFileExists(config_path('cors.php'));
        
        // Check if CORS is properly configured
        $this->assertArrayHasKey('paths', config('cors'));
    }

    /** @test */
    public function it_has_proper_sanctum_configuration()
    {
        $this->assertFileExists(config_path('sanctum.php'));
        
        // Check if Sanctum is properly configured
        $this->assertArrayHasKey('stateful', config('sanctum'));
    }

    /** @test */
    public function it_has_proper_view_configuration()
    {
        $this->assertFileExists(config_path('view.php'));
        
        // Check if views are properly configured
        $this->assertArrayHasKey('paths', config('view'));
    }

    /** @test */
    public function it_has_proper_route_configuration()
    {
        $this->assertFileExists(base_path('routes/web.php'));
        $this->assertFileExists(base_path('routes/api.php'));
        
        // Check if routes are properly loaded
        $this->assertIsArray(app('router')->getRoutes());
    }

    /** @test */
    public function it_has_proper_middleware_configuration()
    {
        $this->assertFileExists(app_path('Http/Kernel.php'));
        
        // Check if middleware is properly configured
        $this->assertArrayHasKey('web', app('router')->getMiddlewareGroups());
        $this->assertArrayHasKey('api', app('router')->getMiddlewareGroups());
    }

    /** @test */
    public function it_has_proper_provider_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if providers are properly configured
        $this->assertIsArray(config('app.providers'));
    }

    /** @test */
    public function it_has_proper_aliases_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if aliases are properly configured
        $this->assertIsArray(config('app.aliases'));
    }

    /** @test */
    public function it_has_proper_timezone_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if timezone is properly configured
        $this->assertArrayHasKey('timezone', config('app'));
    }

    /** @test */
    public function it_has_proper_locale_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if locale is properly configured
        $this->assertArrayHasKey('locale', config('app'));
    }

    /** @test */
    public function it_has_proper_fallback_locale_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if fallback locale is properly configured
        $this->assertArrayHasKey('fallback_locale', config('app'));
    }

    /** @test */
    public function it_has_proper_faker_locale_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if faker locale is properly configured
        $this->assertArrayHasKey('faker_locale', config('app'));
    }

    /** @test */
    public function it_has_proper_key_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if key is properly configured
        $this->assertArrayHasKey('key', config('app'));
    }

    /** @test */
    public function it_has_proper_cipher_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if cipher is properly configured
        $this->assertArrayHasKey('cipher', config('app'));
    }

    /** @test */
    public function it_has_proper_maintenance_mode_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if maintenance mode is properly configured
        $this->assertArrayHasKey('maintenance', config('app'));
    }

    /** @test */
    public function it_has_proper_debug_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if debug is properly configured
        $this->assertArrayHasKey('debug', config('app'));
    }

    /** @test */
    public function it_has_proper_url_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if URL is properly configured
        $this->assertArrayHasKey('url', config('app'));
    }

    /** @test */
    public function it_has_proper_asset_url_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if asset URL is properly configured
        $this->assertArrayHasKey('asset_url', config('app'));
    }

    /** @test */
    public function it_has_proper_force_https_configuration()
    {
        $this->assertFileExists(config_path('app.php'));
        
        // Check if force HTTPS is properly configured
        $this->assertArrayHasKey('force_https', config('app'));
    }
} 