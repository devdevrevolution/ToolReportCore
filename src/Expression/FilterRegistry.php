<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

use Toolreport\Core\Expression\Filter\FilterInterface;

/**
 * Registry of available expression filters.
 *
 * Filters are registered by name and retrieved during expression evaluation.
 * Custom filters can be added before rendering by pushing new FilterInterface instances.
 */
class FilterRegistry
{
    /** @var array<string, FilterInterface> */
    private array $filters = [];

    /**
     * Register a filter instance.
     *
     * If a filter with the same name already exists, it will be replaced.
     */
    public function register(FilterInterface $filter): self
    {
        $this->filters[$filter->name()] = $filter;

        return $this;
    }

    /**
     * Register the default set of filters shipped with ToolReport.
     */
    public function registerDefaults(): self
    {
        $defaults = [
            new Filter\NumberFilter(),
            new Filter\CurrencyFilter(),
            new Filter\UpperFilter(),
            new Filter\LowerFilter(),
            new Filter\TrimFilter(),
            new Filter\DefaultFilter(),
            new Filter\DateFormatFilter(),
            new Filter\IfFilter(),
            new Filter\SubstrFilter(),
            new Filter\ReplaceFilter(),
        ];

        foreach ($defaults as $filter) {
            $this->register($filter);
        }

        return $this;
    }

    /**
     * Get a filter by name.
     *
     * @throws \InvalidArgumentException if the filter is not registered
     */
    public function get(string $name): FilterInterface
    {
        if (!isset($this->filters[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown filter: "%s"', $name));
        }

        return $this->filters[$name];
    }

    /**
     * Check if a filter is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Get all registered filter names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->filters);
    }
}