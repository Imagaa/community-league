<?php

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('membuat berita dengan gambar terkompres, paragraf, dan tampil paling depan carousel', function () {
    Storage::fake('public');
    Post::factory()->create(['type' => 'news', 'sort_order' => 0, 'title' => 'Lama']);

    Livewire::actingAs(buatAdmin())->test('pages::admin.berita-form')
        ->set('title', 'Juara Liga Oktober')
        ->set('excerpt', 'Ringkas')
        ->set('body', "Paragraf satu <b>tebal</b>.\n\nParagraf dua\nbaris dua.")
        ->set('image', UploadedFile::fake()->image('hero.jpg', 1920, 800))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.berita'));

    $post = Post::where('title', 'Juara Liga Oktober')->sole();
    expect($post->body)->toBe('<p>Paragraf satu &lt;b&gt;tebal&lt;/b&gt;.</p><p>Paragraf dua<br>'."\n".'baris dua.</p>')
        ->and($post->image_path)->toEndWith('.webp')
        ->and($post->is_active)->toBeTrue();
    Storage::disk('public')->assertExists($post->image_path);

    $this->get(route('home'))->assertSeeInOrder(['Juara Liga Oktober', 'Lama']);
});

it('promo wajib link dan gambar', function () {
    Livewire::actingAs(buatAdmin())->test('pages::admin.berita-form')
        ->set('type', 'promo')->set('title', 'Diskon')
        ->call('save')
        ->assertHasErrors(['link_url', 'image']);
});

it('mengubah berita tanpa mengganti gambar dan slug', function () {
    $post = Post::factory()->create(['type' => 'news', 'image_path' => 'posts/lama.webp', 'body' => '<p>A</p><p>B</p>']);
    $slug = $post->slug;

    Livewire::actingAs(buatAdmin())->test('pages::admin.berita-form', ['post' => $post])
        ->assertSet('body', "A\n\nB")
        ->set('title', 'Judul Baru')
        ->call('save')->assertHasNoErrors();

    expect($post->fresh())->title->toBe('Judul Baru')->slug->toBe($slug)->image_path->toBe('posts/lama.webp');
});

it('mengurutkan, menonaktifkan, dan menghapus berita', function () {
    Storage::fake('public');
    Storage::disk('public')->put('posts/b.webp', 'x');
    $a = Post::factory()->create(['type' => 'news', 'title' => 'Berita A', 'sort_order' => 1]);
    $b = Post::factory()->create(['type' => 'news', 'title' => 'Berita B', 'sort_order' => 2, 'image_path' => 'posts/b.webp']);

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.berita')
        ->assertSeeInOrder(['Berita A', 'Berita B'])
        ->call('move', $b->id, -1)
        ->assertSeeInOrder(['Berita B', 'Berita A']);

    $page->call('toggle', $a->id);
    expect($a->fresh()->is_active)->toBeFalse();
    $this->get(route('home'))->assertDontSee('Berita A');

    $page->call('delete', $b->id);
    expect(Post::find($b->id))->toBeNull();
    Storage::disk('public')->assertMissing('posts/b.webp');
});

it('halaman berita admin tertutup untuk non-admin', function () {
    $this->get(adminUrl('/berita'))->assertRedirect(adminUrl('/masuk'));
    $this->actingAs(buatAdmin())->get(adminUrl('/berita'))->assertOk()->assertSee('Isi hero carousel homepage');
});
