<?php

namespace Database\Seeders;

use App\Models\Doc;
use App\Models\Faq;
use App\Models\KnownIssue;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds default CMS content. Each table is only seeded while empty, so
 * edits made in the admin panel survive re-deploys.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (PricingPlan::count() === 0) {
            foreach ($this->load('pricing_plans') as $row) {
                PricingPlan::create($row);
            }
        }

        if (Faq::count() === 0) {
            foreach ($this->load('faqs') as $row) {
                Faq::create($row);
            }
        }

        if (Doc::count() === 0) {
            foreach ($this->load('docs') as $row) {
                Doc::create($row);
            }
        }

        if (KnownIssue::count() === 0) {
            foreach ($this->load('known_issues') as $row) {
                KnownIssue::create($row);
            }
        }

        foreach ($this->load('settings') as $key => $value) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::put($key, $value);
            }
        }

        foreach ($this->load('pages') as $row) {
            Page::firstOrCreate(['slug' => $row['slug']], $row);
        }
    }

    private function load(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/content/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
