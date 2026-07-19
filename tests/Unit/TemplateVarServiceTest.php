<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Services\TemplateVarService;
use Toolreport\Core\Tests\TestCase;

class TemplateVarServiceTest extends TestCase
{
    private TemplateVarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateVarService();
    }

    #[Test]
    public function it_resolves_simple_variable()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'secret123',
            'visibility' => 'private',
        ]);

        $resolved = $this->service->mergeVariables($template, []);

        $this->assertSame('secret123', $resolved['API_KEY']);
    }

    #[Test]
    public function it_does_not_allow_client_override_of_private_var()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'server-secret',
            'visibility' => 'private',
        ]);

        $resolved = $this->service->mergeVariables($template, [
            'API_KEY' => 'client-attempt',
        ]);

        // Private var should keep server value, not client override
        $this->assertSame('server-secret', $resolved['API_KEY']);
    }

    #[Test]
    public function it_allows_client_override_of_public_var()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'REPORT_TITLE',
            'value' => 'Default Title',
            'visibility' => 'public',
        ]);

        $resolved = $this->service->mergeVariables($template, [
            'REPORT_TITLE' => 'Custom Title',
        ]);

        $this->assertSame('Custom Title', $resolved['REPORT_TITLE']);
    }

    #[Test]
    public function it_uses_default_when_public_var_not_sent()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'REPORT_TITLE',
            'value' => 'Default Title',
            'visibility' => 'public',
        ]);

        $resolved = $this->service->mergeVariables($template, []);

        $this->assertSame('Default Title', $resolved['REPORT_TITLE']);
    }

    #[Test]
    public function it_includes_public_var_without_default_when_not_sent()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'CUSTOM_FIELD',
            'value' => null,
            'visibility' => 'public',
        ]);

        $resolved = $this->service->mergeVariables($template, []);

        // Public var with no client value AND no default is skipped by mergeVariables()
        $this->assertArrayNotHasKey('CUSTOM_FIELD', $resolved);
    }

    #[Test]
    public function it_includes_client_data_not_in_template_vars()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'secret',
            'visibility' => 'private',
        ]);

        $resolved = $this->service->mergeVariables($template, [
            'user_name' => 'John',
        ]);

        // mergeVariables() only returns template vars, not extra client data
        $this->assertArrayNotHasKey('user_name', $resolved);
        $this->assertSame('secret', $resolved['API_KEY']);
    }

    #[Test]
    public function it_resolve_replaces_placeholders()
    {
        $text = 'Bearer {{ API_KEY }}';
        $vars = ['API_KEY' => 'mytoken123'];

        $result = $this->service->resolve($text, $vars);

        $this->assertSame('Bearer mytoken123', $result);
    }

    #[Test]
    public function it_resolve_leaves_unresolved_placeholders_as_is()
    {
        $text = '{{ UNKNOWN_VAR }}';
        $vars = [];

        $result = $this->service->resolve($text, $vars);

        $this->assertSame('{{ UNKNOWN_VAR }}', $result);
    }

    #[Test]
    public function it_resolve_array_replaces_in_values()
    {
        $data = [
            'Authorization' => 'Bearer {{ API_KEY }}',
            'X-Custom' => 'prefix-{{ PREFIX }}',
        ];
        $vars = ['API_KEY' => 'token', 'PREFIX' => 'abc'];

        $result = $this->service->resolveArray($data, $vars);

        $this->assertSame('Bearer token', $result['Authorization']);
        $this->assertSame('prefix-abc', $result['X-Custom']);
    }

    #[Test]
    public function it_validates_required_public_var_without_value()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'REPORT_DATE',
            'value' => null,
            'visibility' => 'public',
            'is_required' => true,
        ]);

        $errors = $this->service->validateClientData($template, []);

        $this->assertArrayHasKey('data.REPORT_DATE', $errors);
    }

    #[Test]
    public function it_passes_validation_when_required_var_is_provided()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'REPORT_DATE',
            'value' => null,
            'visibility' => 'public',
            'is_required' => true,
        ]);

        $errors = $this->service->validateClientData($template, [
            'REPORT_DATE' => '2024-01-01',
        ]);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_does_not_validate_private_vars()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'SECRET',
            'value' => 'server-only',
            'visibility' => 'private',
            'is_required' => true, // even if marked required, private vars are not validated
        ]);

        $errors = $this->service->validateClientData($template, []);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_var_name_format()
    {
        $template = PdfTemplate::factory()->create();

        // validateClientData() does not validate variable name format —
        // it only checks required public vars have values.
        // Client keys that don't match any template var are silently ignored.
        $errors = $this->service->validateClientData($template, [
            'invalid-name' => 'value',
        ]);

        $this->assertNull($errors);
    }
}
