<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JavaScriptFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_javascript_modules_are_loaded()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that JavaScript modules are loaded
        $response->assertSee('emailList.js');
        $response->assertSee('search.js');
        $response->assertSee('upload.js');
        
        // Check that the main app.js is loaded
        $response->assertSee('app.js');
    }

    public function test_upload_functionality_is_available()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that upload form elements exist
        $response->assertSee('upload-form');
        $response->assertSee('upload-input');
        $response->assertSee('upload-btn');
    }

    public function test_search_functionality_is_available()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that search form elements exist
        $response->assertSee('search-form');
        $response->assertSee('search');
        $response->assertSee('label_id');
    }

    public function test_email_list_functionality_is_available()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that email list elements exist
        $response->assertSee('email-list');
        $response->assertSee('email-items');
        $response->assertSee('email-detail');
    }

    public function test_tailwind_css_is_loaded()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that Tailwind CSS classes are present
        $response->assertSee('bg-gray-50');
        $response->assertSee('text-xl');
        $response->assertSee('px-4');
    }

    public function test_font_awesome_is_loaded()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Check that Font Awesome is loaded
        $response->assertSee('font-awesome');
        $response->assertSee('fas');
    }
} 