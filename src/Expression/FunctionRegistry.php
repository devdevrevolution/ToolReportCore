<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Registry of available expression functions.
 *
 * Functions are registered by name and looked up case-insensitively.
 * Contains both legacy wrapped filters and new Excel-style functions.
 */
class FunctionRegistry
{
    /** @var array<string, FunctionInterface> normalized to lowercase keys */
    private array $functions = [];

    /**
     * Register a function instance.
     *
     * If a function with the same name already exists, it will be replaced.
     */
    public function register(FunctionInterface $fn): self
    {
        $this->functions[strtolower($fn->name())] = $fn;

        return $this;
    }

    /**
     * Get a function by name (case-insensitive).
     *
     * @throws \InvalidArgumentException if the function is not registered
     */
    public function get(string $name): FunctionInterface
    {
        $key = strtolower($name);

        if (!isset($this->functions[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown function: "%s"', $name));
        }

        return $this->functions[$key];
    }

    /**
     * Check if a function is registered (case-insensitive).
     */
    public function has(string $name): bool
    {
        return isset($this->functions[strtolower($name)]);
    }

    /**
     * Get all registered function names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_map(
            fn (FunctionInterface $fn) => $fn->name(),
            $this->functions,
        )));
    }

    /**
     * Create a registry with all default functions.
     *
     * Includes:
     * - Legacy filters wrapped via LegacyFilterAdapter
     * - New math functions
     * - New text functions
     * - New date functions
     * - New formatting functions
     */
    public static function defaults(): self
    {
        $registry = new self();

        // Legacy filters (wrapped)
        $registry->register(new LegacyFilterAdapter(new Filter\NumberFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\CurrencyFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\UpperFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\LowerFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\TrimFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\DefaultFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\DateFormatFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\IfFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\SubstrFilter()));
        $registry->register(new LegacyFilterAdapter(new Filter\ReplaceFilter()));

        // Math functions
        $registry->register(new Function\Math\SumFunction());
        $registry->register(new Function\Math\MultiplyFunction());
        $registry->register(new Function\Math\DivideFunction());
        $registry->register(new Function\Math\AddFunction());
        $registry->register(new Function\Math\SubtractFunction());
        $registry->register(new Function\Math\ModFunction());
        $registry->register(new Function\Math\PowFunction());
        $registry->register(new Function\Math\SqrtFunction());
        $registry->register(new Function\Math\AbsFunction());
        $registry->register(new Function\Math\RoundFunction());
        $registry->register(new Function\Math\CeilFunction());
        $registry->register(new Function\Math\FloorFunction());
        $registry->register(new Function\Math\MinFunction());
        $registry->register(new Function\Math\MaxFunction());
        $registry->register(new Function\Math\ClampFunction());

        // Text functions
        $registry->register(new Function\Text\UpperFunction());
        $registry->register(new Function\Text\LowerFunction());
        $registry->register(new Function\Text\TrimFunction());
        $registry->register(new Function\Text\SubstrFunction());
        $registry->register(new Function\Text\ReplaceFunction());
        $registry->register(new Function\Text\ConcatFunction());
        $registry->register(new Function\Text\LeftFunction());
        $registry->register(new Function\Text\RightFunction());
        $registry->register(new Function\Text\MidFunction());
        $registry->register(new Function\Text\LenFunction());

        // Date functions
        $registry->register(new Function\Date\FormatDateFunction());
        $registry->register(new Function\Date\DateAddFunction());
        $registry->register(new Function\Date\DateDiffFunction());

        // Logic functions
        $registry->register(new Function\Logic\IfFunction());
        $registry->register(new Function\Logic\DefaultFunction());

        // Formatting functions
        $registry->register(new Function\Formatting\FormatNumberFunction());
        $registry->register(new Function\Formatting\FormatCurrencyFunction());
        $registry->register(new Function\Formatting\ParseNumberFunction());

        return $registry;
    }
}
