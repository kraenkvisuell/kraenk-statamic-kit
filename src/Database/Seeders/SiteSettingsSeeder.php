<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

/**
 * Lorem ipsum content for the `site_settings` global set of a fresh site:
 * contact data and address (the contact section above the footer), social
 * links and newsletter, the video consent texts, and the Datenschutz page as
 * privacy page. Written to the default site's variables; the other sites
 * inherit them through the origins in content/globals/site_settings.yaml.
 *
 * Idempotent: variables that already hold data are left alone. Runs after
 * DemoPagesSeeder (privacy page).
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\SiteSettingsSeeder"
 */
class SiteSettingsSeeder extends Seeder
{
    protected string $set = 'site_settings';

    protected string $privacySlug = 'datenschutz';

    protected function values(): array
    {
        return [
            'contact_topline' => "Lorem Ipsum GmbH\nIhr Ansprechpartner: Dolor Sit",
            'phone' => '+49 30 123 456 78',
            'email' => 'lorem@example.com',
            'second_contact_topline' => "Consectetur Adipiscing\nProduktion",
            'second_phone' => '+49 30 876 543 21',
            'second_email' => 'ipsum@example.com',
            'company' => 'Lorem Ipsum GmbH',
            'fax' => '+49 30 123 456 79',
            'address' => "Dolorstraße 12\n12345 Berlin",
            'gmaps_link' => 'https://maps.google.com/?q=Dolorstra%C3%9Fe+12,+12345+Berlin',
            'social_links_topline' => 'Folgen Sie uns',
            'social_links' => [
                ['id' => Str::random(8), 'type' => 'social_link', 'enabled' => true, 'title' => 'Instagram', 'link' => 'https://www.instagram.com/loremipsum'],
                ['id' => Str::random(8), 'type' => 'social_link', 'enabled' => true, 'title' => 'Facebook', 'link' => 'https://www.facebook.com/loremipsum'],
                ['id' => Str::random(8), 'type' => 'social_link', 'enabled' => true, 'title' => 'Vimeo', 'link' => 'https://vimeo.com/loremipsum'],
            ],
            'newsletter_text' => 'Lorem ipsum dolor sit amet – Neues aus dem Studio, einmal im Monat.',
            'newsletter_link' => 'https://example.com/newsletter',
            'video_consent_text' => 'Dieses Video wird von Vimeo bzw. YouTube bereitgestellt. Mit dem Abspielen werden Daten an den Anbieter übertragen.',
            'video_consent_button' => 'Videos akzeptieren',
            'video_consent_revoke' => 'Zustimmung zu Videos widerrufen',
        ];
    }

    public function run(): void
    {
        $default = Site::default()->handle();
        $variables = GlobalSet::find($this->set)?->in($default);

        if (! $variables) {
            $this->command?->warn("Global set {$this->set} has no variables for site {$default}; run the migrations first.");

            return;
        }

        if ($variables->data()->isNotEmpty()) {
            $this->command?->info("Site settings ({$default}) hold data already, left as they are.");

            return;
        }

        $privacy = Entry::query()
            ->where('collection', 'pages')
            ->where('site', $default)
            ->where('slug', $this->privacySlug)
            ->first();

        $variables->data([
            ...$this->values(),
            'privacy_page' => $privacy ? [$privacy->id()] : [],
        ])->save();

        $this->command?->info("Site settings ({$default}) seeded with lorem ipsum contact data".($privacy ? ', privacy page linked.' : '.'));
    }
}
