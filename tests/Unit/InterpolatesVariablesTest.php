<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Toolreport\Core\Layout\InterpolatesVariables;
use Toolreport\Core\Tests\TestCase;

class InterpolatesVariablesTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Use an anonymous class to expose the protected trait methods for testing
        $this->traitUser = new class {
            use InterpolatesVariables {
                interpolate as public;
                arrayGet as public;
                resolveVariableKey as public;
                getFilterRegistry as public;
            }
        };
    }

    #[Test]
    public function it_resolves_from_local_data_first(): void
    {
        $result = $this->traitUser->interpolate(
            'Hello {{name}}',
            ['name' => 'Global'],
            ['name' => 'Local']
        );

        $this->assertEquals('Hello Local', $result);
    }

    #[Test]
    public function it_falls_back_to_global_data_when_local_lacks_key(): void
    {
        $result = $this->traitUser->interpolate(
            'Hello {{name}}',
            ['name' => 'Global'],
            ['other' => 'value']
        );

        $this->assertEquals('Hello Global', $result);
    }

    #[Test]
    public function it_leaves_unresolved_placeholder_as_is(): void
    {
        $result = $this->traitUser->interpolate(
            'Hello {{missing}}',
            [],
            []
        );

        $this->assertEquals('Hello {{missing}}', $result);
    }

    #[Test]
    public function it_resolves_dot_notation_path_from_data(): void
    {
        $result = $this->traitUser->interpolate(
            'City: {{address.city}}',
            ['address' => ['city' => 'NYC']],
            []
        );

        $this->assertEquals('City: NYC', $result);
    }

    #[Test]
    public function it_resolves_dot_notation_path_from_local_data(): void
    {
        $result = $this->traitUser->interpolate(
            'City: {{address.city}}',
            ['address' => ['city' => 'Global City']],
            ['address' => ['city' => 'Local City']]
        );

        $this->assertEquals('City: Local City', $result);
    }

    #[Test]
    public function it_local_data_shadows_global_data(): void
    {
        $result = $this->traitUser->interpolate(
            '{{name}}',
            ['name' => 'Global Widget'],
            ['name' => 'Local Widget']
        );

        $this->assertEquals('Local Widget', $result);
    }

    #[Test]
    public function it_handles_empty_data_arrays(): void
    {
        $result = $this->traitUser->interpolate(
            'Hello {{name}}',
            [],
            []
        );

        $this->assertEquals('Hello {{name}}', $result);
    }

    #[Test]
    public function it_resolves_multiple_placeholders_in_one_string(): void
    {
        $result = $this->traitUser->interpolate(
            '{{greeting}} {{name}}, your order #{{order.id}} is ready',
            ['greeting' => 'Hello', 'name' => 'John', 'order' => ['id' => '123']],
            []
        );

        $this->assertEquals('Hello John, your order #123 is ready', $result);
    }

    #[Test]
    public function it_casts_numeric_values_to_string(): void
    {
        $result = $this->traitUser->interpolate(
            'Total: {{amount}}',
            ['amount' => 42.5],
            []
        );

        $this->assertEquals('Total: 42.5', $result);
    }

    #[Test]
    public function it_resolves_integer_from_local_data(): void
    {
        $result = $this->traitUser->interpolate(
            '{{id}}',
            ['id' => 999],
            ['id' => 101]
        );

        $this->assertEquals('101', $result);
    }

    #[Test]
    public function arrayGet_resolves_dot_notation(): void
    {
        $result = $this->traitUser->arrayGet(
            ['client' => ['name' => 'Acme', 'address' => ['city' => 'NYC']]],
            'client.address.city'
        );

        $this->assertEquals('NYC', $result);
    }

    #[Test]
    public function arrayGet_returns_default_for_missing_key(): void
    {
        $result = $this->traitUser->arrayGet(
            ['name' => 'Test'],
            'missing.key',
            'fallback'
        );

        $this->assertEquals('fallback', $result);
    }

    #[Test]
    public function arrayGet_returns_null_by_default(): void
    {
        $result = $this->traitUser->arrayGet(
            ['name' => 'Test'],
            'missing.key'
        );

        $this->assertNull($result);
    }

    #[Test]
    public function it_resolves_bracket_notation_from_local_data(): void
    {
        $result = $this->traitUser->interpolate(
            'Cognome: {{ [].cognome }}',
            ['cognome' => 'Global'],
            ['cognome' => 'Rossi']
        );

        $this->assertEquals('Cognome: Rossi', $result);
    }

    #[Test]
    public function it_resolves_bracket_notation_with_nested_path_from_local_data(): void
    {
        $result = $this->traitUser->interpolate(
            'City: {{ [].address.city }}',
            ['address' => ['city' => 'Global City']],
            ['address' => ['city' => 'Local City']]
        );

        $this->assertEquals('City: Local City', $result);
    }

    #[Test]
    public function bracket_notation_does_not_fall_back_to_global_data(): void
    {
        // []. always resolves from localData only — it means "current iteration item"
        $result = $this->traitUser->interpolate(
            '{{ [].cognome }}',
            ['cognome' => 'Global Cognome'],
            []
        );

        // Should leave placeholder unresolved since localData is empty
        $this->assertEquals('{{ [].cognome }}', $result);
    }

    #[Test]
    public function it_resolves_parent_bracket_notation_from_local_data(): void
    {
        // orders[].total should resolve 'total' from localData (current iteration item)
        $result = $this->traitUser->interpolate(
            'Total: {{ orders[].total }}',
            ['orders' => [['total' => 99], ['total' => 50]]],
            ['total' => 29.99, 'product' => 'Widget']
        );

        $this->assertEquals('Total: 29.99', $result);
    }

    #[Test]
    public function it_resolves_bracket_notation_with_nested_local_data(): void
    {
        $localData = ['cognome' => 'Rossi', 'eta' => 35, 'indirizzo' => ['citta' => 'Roma']];
        $data = ['items' => []];

        $result = $this->traitUser->interpolate(
            '{{ [].cognome }}, {{ [].eta }}, {{ [].indirizzo.citta }}',
            $data,
            $localData
        );

        $this->assertEquals('Rossi, 35, Roma', $result);
    }

    #[Test]
    public function it_handles_bracket_notation_not_found_in_local_data(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [].missing }}',
            ['missing' => 'Global Value'],
            ['other' => 'Local Value']
        );

        // []. does not fall back to global data
        $this->assertEquals('{{ [].missing }}', $result);
    }

    #[Test]
    public function it_mixes_bracket_notation_and_regular_placeholders(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [].cognome }} - {{ company }}',
            ['company' => 'Acme Corp'],
            ['cognome' => 'Rossi']
        );

        $this->assertEquals('Rossi - Acme Corp', $result);
    }

    #[Test]
    public function it_resolves_bracket_notation_in_root_level_array_scenario(): void
    {
        // Simulates a root-level array API response where each item has "cognome"
        $localData = ['id' => 1, 'cognome' => 'Bianchi', 'nome' => 'Luca'];
        $data = []; // Root data is empty when iterating root-level array

        $result = $this->traitUser->interpolate(
            '{{ [].cognome }}, {{ [].nome }}',
            $data,
            $localData
        );

        $this->assertEquals('Bianchi, Luca', $result);
    }

    // ─── [N] index notation ─────────────────────────────────────────────

    #[Test]
    public function it_resolves_indexed_bracket_with_field_from_global_data(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [0].name }}',
            [
                ['name' => 'Alpha'],
                ['name' => 'Beta'],
            ],
            []
        );

        $this->assertEquals('Alpha', $result);
    }

    #[Test]
    public function it_resolves_different_index_in_global_data(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [1].name }}',
            [
                ['name' => 'Alpha'],
                ['name' => 'Beta'],
            ],
            []
        );

        $this->assertEquals('Beta', $result);
    }

    #[Test]
    public function it_resolves_nested_dot_notation_with_indexed_bracket(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [0].address.city }}',
            [
                ['address' => ['city' => 'NYC']],
                ['address' => ['city' => 'LA']],
            ],
            []
        );

        $this->assertEquals('NYC', $result);
    }

    #[Test]
    public function it_leaves_placeholder_when_index_out_of_bounds(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [999].name }}',
            [['name' => 'Only']],
            []
        );

        $this->assertEquals('{{ [999].name }}', $result);
    }

    #[Test]
    public function it_leaves_placeholder_when_indexed_field_missing(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [0].missing }}',
            [['name' => 'Item']],
            []
        );

        $this->assertEquals('{{ [0].missing }}', $result);
    }

    // ─── parent[N] nested index notation ────────────────────────────────

    #[Test]
    public function it_resolves_nested_parent_index_with_field(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ orders[0].name }}',
            [
                'orders' => [
                    ['name' => 'Order A'],
                    ['name' => 'Order B'],
                ],
            ],
            []
        );

        $this->assertEquals('Order A', $result);
    }

    #[Test]
    public function it_resolves_nested_parent_index_second_item(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ orders[1].name }}',
            [
                'orders' => [
                    ['name' => 'Order A'],
                    ['name' => 'Order B'],
                ],
            ],
            []
        );

        $this->assertEquals('Order B', $result);
    }

    #[Test]
    public function it_leaves_placeholder_when_parent_not_found(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ missing[0].name }}',
            ['orders' => [['name' => 'A']]],
            []
        );

        $this->assertEquals('{{ missing[0].name }}', $result);
    }

    #[Test]
    public function it_leaves_placeholder_when_nested_index_out_of_bounds(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ orders[99].name }}',
            ['orders' => [['name' => 'A']]],
            []
        );

        $this->assertEquals('{{ orders[99].name }}', $result);
    }

    // ─── Filter expression tests ──────────────────────────────────

    #[Test]
    public function it_applies_number_filter(): void
    {
        $result = $this->traitUser->interpolate(
            'Total: {{ amount | number(2) }}',
            ['amount' => 1234.567],
            []
        );

        $this->assertEquals('Total: 1,234.57', $result);
    }

    #[Test]
    public function it_applies_number_filter_with_custom_separators(): void
    {
        $result = $this->traitUser->interpolate(
            'Total: {{ amount | number(2, ",", ".") }}',
            ['amount' => 1234.56],
            []
        );

        $this->assertEquals('Total: 1.234,56', $result);
    }

    #[Test]
    public function it_applies_number_filter_with_zero_decimals(): void
    {
        $result = $this->traitUser->interpolate(
            'Count: {{ count | number(0) }}',
            ['count' => 1234.5],
            []
        );

        $this->assertEquals('Count: 1,235', $result);
    }

    #[Test]
    public function it_applies_currency_filter_with_defaults(): void
    {
        $result = $this->traitUser->interpolate(
            'Price: {{ price | currency("$") }}',
            ['price' => 1234.56],
            []
        );

        $this->assertEquals('Price: $1,234.56', $result);
    }

    #[Test]
    public function it_applies_currency_filter_after_position(): void
    {
        $result = $this->traitUser->interpolate(
            'Price: {{ price | currency("€", 2, ",", ".", "after") }}',
            ['price' => 1234.56],
            []
        );

        $this->assertEquals('Price: 1.234,56 €', $result);
    }

    #[Test]
    public function it_applies_currency_filter_with_zero_decimals(): void
    {
        $result = $this->traitUser->interpolate(
            'Price: {{ price | currency("$", 0) }}',
            ['price' => 1234.56],
            []
        );

        $this->assertEquals('Price: $1,235', $result);
    }

    #[Test]
    public function it_applies_upper_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | upper }}',
            ['name' => 'john doe'],
            []
        );

        $this->assertEquals('JOHN DOE', $result);
    }

    #[Test]
    public function it_applies_lower_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | lower }}',
            ['name' => 'JOHN DOE'],
            []
        );

        $this->assertEquals('john doe', $result);
    }

    #[Test]
    public function it_applies_trim_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | trim }}',
            ['name' => '  john  '],
            []
        );

        $this->assertEquals('john', $result);
    }

    #[Test]
    public function it_applies_default_filter_with_null(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ phone | default("N/A") }}',
            ['phone' => null],
            []
        );

        // null values in data array still resolve, but the value is null
        $this->assertEquals('N/A', $result);
    }

    #[Test]
    public function it_applies_default_filter_with_empty_string(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | default("Sin nombre") }}',
            ['name' => ''],
            []
        );

        $this->assertEquals('Sin nombre', $result);
    }

    #[Test]
    public function it_applies_default_filter_with_existing_value(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | default("Sin nombre") }}',
            ['name' => 'John'],
            []
        );

        $this->assertEquals('John', $result);
    }

    #[Test]
    public function it_applies_date_filter_with_format(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ created_at | date("d/m/Y") }}',
            ['created_at' => '2026-06-13'],
            []
        );

        $this->assertEquals('13/06/2026', $result);
    }

    #[Test]
    public function it_applies_date_filter_with_timestamp(): void
    {
        $timestamp = strtotime('2026-06-13');
        $result = $this->traitUser->interpolate(
            '{{ created_at | date("Y-m-d") }}',
            ['created_at' => $timestamp],
            []
        );

        $this->assertEquals('2026-06-13', $result);
    }

    #[Test]
    public function it_applies_if_filter_with_string_match(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ status | if("active", "Activo", "Inactivo") }}',
            ['status' => 'active'],
            []
        );

        $this->assertEquals('Activo', $result);
    }

    #[Test]
    public function it_applies_if_filter_with_string_no_match(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ status | if("active", "Activo", "Inactivo") }}',
            ['status' => 'pending'],
            []
        );

        $this->assertEquals('Inactivo', $result);
    }

    #[Test]
    public function it_applies_if_filter_with_boolean_true(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ is_paid | if(true, "Pagado", "Pendiente") }}',
            ['is_paid' => true],
            []
        );

        $this->assertEquals('Pagado', $result);
    }

    #[Test]
    public function it_applies_if_filter_with_boolean_false(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ is_paid | if(true, "Pagado", "Pendiente") }}',
            ['is_paid' => false],
            []
        );

        $this->assertEquals('Pendiente', $result);
    }

    #[Test]
    public function it_applies_if_filter_with_number_match(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ qty | if(0, "Sin stock", "Disponible") }}',
            ['qty' => 0],
            []
        );

        $this->assertEquals('Sin stock', $result);
    }

    #[Test]
    public function it_applies_substring_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ desc | substr(0, 5) }}',
            ['desc' => 'Hello World'],
            []
        );

        $this->assertEquals('Hello', $result);
    }

    #[Test]
    public function it_applies_replace_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ code | replace("_", " ") }}',
            ['code' => 'hello_world'],
            []
        );

        $this->assertEquals('hello world', $result);
    }

    #[Test]
    public function it_chains_multiple_filters(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | trim | upper }}',
            ['name' => '  john doe  '],
            []
        );

        $this->assertEquals('JOHN DOE', $result);
    }

    #[Test]
    public function it_chains_number_then_default(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ amount | number(2) | default("0.00") }}',
            ['amount' => null],
            []
        );

        // number(2) on null returns null, default("0.00") catches it
        $this->assertEquals('0.00', $result);
    }

    #[Test]
    public function it_maintains_backward_compatibility_with_plain_variables(): void
    {
        $result = $this->traitUser->interpolate(
            'Hello {{ name }}, your total is {{ total }}',
            ['name' => 'John', 'total' => 42.5],
            []
        );

        $this->assertEquals('Hello John, your total is 42.5', $result);
    }

    #[Test]
    public function it_uses_filter_with_bracket_notation(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ [].price | currency("$") }}',
            ['price' => 100],
            ['price' => 99.99]
        );

        $this->assertEquals('$99.99', $result);
    }

    #[Test]
    public function it_uses_filter_with_dot_notation(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ client.name | upper }}',
            ['client' => ['name' => 'acme corp']],
            []
        );

        $this->assertEquals('ACME CORP', $result);
    }

    #[Test]
    public function it_handles_null_value_with_number_filter(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ amount | number(2) | default("0.00") }}',
            ['missing_key' => 'value'],
            []
        );

        // Variable not found → placeholder left, then number filter can't parse → still placeholder
        // Actually: variable not found → null → number(null) → null → default("0.00") → "0.00"
        // But resolveVariableKey returns null only when found as null. Missing key returns the placeholder.
        // So the placeholder stays as-is since it's not in data.
        $this->assertEquals('0.00', $result);
    }

    #[Test]
    public function it_leaves_unknown_filter_intact(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ name | unknown_filter }}',
            ['name' => 'John'],
            []
        );

        // Unknown filter is skipped, value is still resolved
        $this->assertEquals('John', $result);
    }

    #[Test]
    public function it_converts_integer_to_currency(): void
    {
        $result = $this->traitUser->interpolate(
            '{{ price | currency("USD ", 0) }}',
            ['price' => 2500],
            []
        );

        $this->assertEquals('USD 2,500', $result);
    }

    #[Test]
    public function it_applies_filter_with_single_quoted_params(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ status | if('active', 'Activo', 'Inactivo') }}",
            ['status' => 'active'],
            []
        );

        $this->assertEquals('Activo', $result);
    }

    // ─── Concatenation and literal string tests ────────────────────

    #[Test]
    public function it_concatenates_literal_string_with_variable(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Total: ' + price | currency('$') }}",
            ['price' => 1234.56],
            []
        );

        $this->assertEquals('Total: $1,234.56', $result);
    }

    #[Test]
    public function it_concatenates_literal_with_filtered_variable_no_spaces(): void
    {
        // User's original request: 'Richiesta:\n' + house.price | currency(...)
        $result = $this->traitUser->interpolate(
            "{{ 'Richiesta:' + price | currency('$', 2) }}",
            ['price' => 99.99],
            []
        );

        $this->assertEquals('Richiesta:$99.99', $result);
    }

    #[Test]
    public function it_concatenates_with_newline_escape(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Richiesta:\\n' + price | currency('$') }}",
            ['price' => 99.99],
            []
        );

        $this->assertEquals("Richiesta:\n$99.99", $result);
    }

    #[Test]
    public function it_concatenates_variable_with_literal_suffix(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ name | upper + '!' }}",
            ['name' => 'john'],
            []
        );

        $this->assertEquals('JOHN!', $result);
    }

    #[Test]
    public function it_concatenates_prefix_variable_and_suffix(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ '[' + code + '] ' + name }}",
            ['code' => 'ABC', 'name' => 'Widget'],
            []
        );

        $this->assertEquals('[ABC] Widget', $result);
    }

    #[Test]
    public function it_concatenates_multiple_literals_and_variables(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Hello ' + name + ', your total is ' + total | currency('$') }}",
            ['name' => 'John', 'total' => 42.5],
            []
        );

        $this->assertEquals('Hello John, your total is $42.50', $result);
    }

    #[Test]
    public function it_concatenates_with_unresolved_variable_as_empty(): void
    {
        // In concatenation context, missing variables become empty string
        $result = $this->traitUser->interpolate(
            "{{ 'Total: ' + missing_var | default('N/A') }}",
            [],
            []
        );

        $this->assertEquals('Total: N/A', $result);
    }

    #[Test]
    public function it_concatenates_literal_only(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Static Text' }}",
            [],
            []
        );

        $this->assertEquals('Static Text', $result);
    }

    #[Test]
    public function it_concatenates_with_tab_escape(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Name\\t' + name }}",
            ['name' => 'John'],
            []
        );

        $this->assertEquals("Name\tJohn", $result);
    }

    #[Test]
    public function it_concatenates_bracket_notation_variable(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Price: ' + [].price | currency('$') }}",
            ['price' => 100],
            ['price' => 50]
        );

        $this->assertEquals('Price: $50.00', $result);
    }

    #[Test]
    public function it_concatenates_with_if_filter(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Status: ' + status | if('active', 'Active', 'Inactive') }}",
            ['status' => 'active'],
            []
        );

        $this->assertEquals('Status: Active', $result);
    }

    #[Test]
    public function it_preserves_backward_compat_for_single_missing_variable(): void
    {
        // Single unresolved variable (no concatenation) still leaves placeholder
        $result = $this->traitUser->interpolate(
            '{{ missing_var }}',
            [],
            []
        );

        $this->assertEquals('{{ missing_var }}', $result);
    }

    #[Test]
    public function it_concatenates_number_variable_with_literal(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Count: ' + count }}",
            ['count' => 42],
            []
        );

        $this->assertEquals('Count: 42', $result);
    }

    #[Test]
    public function it_concatenates_with_date_filter(): void
    {
        $result = $this->traitUser->interpolate(
            "{{ 'Date: ' + created_at | date('d/m/Y') }}",
            ['created_at' => '2026-06-13'],
            []
        );

        $this->assertEquals('Date: 13/06/2026', $result);
    }
}