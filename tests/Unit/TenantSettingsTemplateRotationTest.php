<?php

use App\Models\TenantSettings;

it('provides multiple default variants for wa templates', function () {
    $settings = new TenantSettings;

    expect($settings->getDefaultTemplateVariants('registration'))->toHaveCount(3)
        ->and($settings->getDefaultTemplateVariants('invoice'))->toHaveCount(3)
        ->and($settings->getDefaultTemplateVariants('payment'))->toHaveCount(3);
});

it('parses custom template rotation using separator line', function () {
    $settings = new TenantSettings([
        'wa_template_invoice' => "Template A\n---\nTemplate B\n---\nTemplate C",
    ]);

    expect($settings->getTemplateVariants('invoice'))->toBe([
        'Template A',
        'Template B',
        'Template C',
    ]);
});

it('falls back to default template variants when custom template is empty', function () {
    $settings = new TenantSettings([
        'wa_template_registration' => " \n ",
    ]);

    $variants = $settings->getTemplateVariants('registration');

    expect($variants)->toHaveCount(3)
        ->and($settings->getTemplate('registration'))->toBe($variants[0]);
});
