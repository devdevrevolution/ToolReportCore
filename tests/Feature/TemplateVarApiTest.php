<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Tests\TestCase;

class TemplateVarApiTest extends TestCase
{
    #[Test]
    public function it_lists_template_vars_for_template()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'secret',
            'visibility' => 'private',
        ]);

        $response = $this->getJson("/api/pdf-designer/templates/{$template->id}/template-vars");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        // Private var value should be masked
        $response->assertJsonPath('data.0.value', '***');
    }

    #[Test]
    public function it_creates_template_var()
    {
        $template = PdfTemplate::factory()->create();

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/template-vars", [
            'name' => 'API_KEY',
            'value' => 'secret123',
            'visibility' => 'private',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'API_KEY');
        $response->assertJsonPath('data.visibility', 'private');
    }

    #[Test]
    public function it_updates_template_var()
    {
        $template = PdfTemplate::factory()->create();
        $templateVar = $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'old-value',
            'visibility' => 'private',
        ]);

        $response = $this->putJson(
            "/api/pdf-designer/templates/{$template->id}/template-vars/{$templateVar->id}",
            ['value' => 'new-value']
        );

        $response->assertOk();
        $response->assertJsonPath('data.name', 'API_KEY');
    }

    #[Test]
    public function it_preserves_private_var_when_client_sends_masked_value()
    {
        $template = PdfTemplate::factory()->create();
        $templateVar = $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'original-secret',
            'visibility' => 'private',
        ]);

        // Client sends *** (masked value from GET response)
        $response = $this->putJson(
            "/api/pdf-designer/templates/{$template->id}/template-vars/{$templateVar->id}",
            ['value' => '***']
        );

        $response->assertOk();

        // Value should remain unchanged
        $templateVar->refresh();
        $this->assertSame('original-secret', $templateVar->value);
    }

    #[Test]
    public function it_deletes_template_var()
    {
        $template = PdfTemplate::factory()->create();
        $templateVar = $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'secret',
            'visibility' => 'private',
        ]);

        $response = $this->deleteJson(
            "/api/pdf-designer/templates/{$template->id}/template-vars/{$templateVar->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('template_vars', ['id' => $templateVar->id]);
    }

    #[Test]
    public function it_validates_name_format()
    {
        $template = PdfTemplate::factory()->create();

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/template-vars", [
            'name' => 'invalid-name',
            'value' => 'value',
            'visibility' => 'public',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_unique_name_per_template()
    {
        $template = PdfTemplate::factory()->create();
        $template->templateVars()->create([
            'name' => 'API_KEY',
            'value' => 'value',
            'visibility' => 'private',
        ]);

        $response = $this->postJson("/api/pdf-designer/templates/{$template->id}/template-vars", [
            'name' => 'API_KEY',
            'value' => 'another',
            'visibility' => 'public',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }
}
