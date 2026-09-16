<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

describe('Blog module', function () {
    it('passes the configured module title to the inertia page', function () {
        $this->actingAs(User::factory()->create());

        $this->get('/m/blogs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Index')
                ->where('titles.page', 'Blogs')
            );
    });
});
