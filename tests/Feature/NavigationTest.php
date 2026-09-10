<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavigationTest extends TestCase
{
    public function test_sidebar_starts_with_only_the_current_page_group_expanded(): void
    {
        $this->actingAs($this->rootUser());

        foreach ([
            'admin.dashboard' => 'overview',
            'users.index' => 'management',
            'roles.index' => 'management',
            'profile.edit' => 'workspace',
            'settings.general' => 'settings',
            'admin.settings.smtp' => 'settings',
        ] as $route => $expectedGroup) {
            $response = $this->get(route($route))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $expanded = $xpath->query('//aside[@id="sidebar"]//button[@data-nav-toggle and @aria-expanded="true"]');
            $this->assertCount(1, $expanded, $route.' should open exactly one group.');
            $this->assertSame($expectedGroup, $expanded->item(0)->parentNode->getAttribute('data-nav-key'));
        }
    }

    public function test_horizontal_menus_wait_for_interaction_while_mobile_keeps_the_current_group(): void
    {
        $root = $this->rootUser();
        $root->update(['layout' => 'horizontal']);
        $response = $this->actingAs($root)->get(route('settings.general'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertCount(0, $xpath->query('//nav[@class="horizontal-nav"]//button[@aria-expanded="true"]'));
        $this->assertCount(1, $xpath->query('//aside[@id="sidebar"]//div[@data-nav-key="settings"]/button[@aria-expanded="true"]'));
    }
}
