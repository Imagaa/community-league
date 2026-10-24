<?php

use App\Models\Post;

it('tamu bisa membuka halaman detail berita yang sudah terbit', function () {
    $post = Post::factory()->create(['title' => 'Matchday Solo', 'body' => '<p>Isi lengkap berita</p>']);

    $this->get(route('berita.show', $post))
        ->assertOk()
        ->assertSee('Matchday Solo')
        ->assertSee('Isi lengkap berita', false);
});

it('halaman berita 404 kalau post masih draft', function () {
    $post = Post::factory()->draft()->create();

    $this->get(route('berita.show', $post))->assertNotFound();
});

it('halaman berita 404 kalau belum waktunya terbit', function () {
    $post = Post::factory()->create(['published_at' => now()->addDay()]);

    $this->get(route('berita.show', $post))->assertNotFound();
});

it('halaman berita 404 untuk post tipe promo', function () {
    $post = Post::factory()->promo()->create();

    $this->get(route('berita.show', $post))->assertNotFound();
});

it('halaman berita 404 untuk post tipe galeri', function () {
    $post = Post::factory()->create(['type' => 'carousel']);

    $this->get(route('berita.show', $post))->assertNotFound();
});
