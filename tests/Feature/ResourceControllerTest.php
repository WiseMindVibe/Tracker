<?php

use Inertia\Testing\AssertableInertia;

describe('ResourceController', function () {
    it('passes the configured resource title to the inertia page', function () {
        $this->get('/resources/blogs')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Resource/index')
                ->where('title', 'Blogs List')
            );
    });
});
