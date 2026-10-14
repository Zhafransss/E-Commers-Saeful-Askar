<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample articles (published, published, draft) matching docs/api/articles.md examples.
 */
class DemoArticleSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', UserRole::Admin)->first();

        $articles = [
            [
                'slug' => 'cara-merawat-baterai-sepeda-listrik',
                'title' => 'Cara Merawat Baterai Sepeda Listrik',
                'body' => '<p>Lima kebiasaan sederhana agar baterai lithium awet hingga 3 tahun.</p><h2>1. Jangan biarkan kosong</h2><p>Isi ulang sebelum daya di bawah 20%.</p>',
                'status' => ArticleStatus::Published,
                'published_at' => now()->subDays(5),
            ],
            [
                'slug' => 'memilih-sepeda-listrik-untuk-harian',
                'title' => 'Memilih Sepeda Listrik untuk Pemakaian Harian',
                'body' => '<p>Perhatikan daya motor, kapasitas baterai, dan berat sepeda sebelum membeli.</p>',
                'status' => ArticleStatus::Published,
                'published_at' => now()->subDays(14),
            ],
            [
                'slug' => 'promo-akhir-tahun-sepeda-listrik',
                'title' => 'Promo Akhir Tahun Sepeda Listrik',
                'body' => '<p>Draft isi promo akhir tahun.</p>',
                'status' => ArticleStatus::Draft,
                'published_at' => null,
            ],
        ];

        foreach ($articles as $data) {
            $article = Article::updateOrCreate(['slug' => $data['slug']], $data);
            $article->author()->associate($author)->save();
        }
    }
}
