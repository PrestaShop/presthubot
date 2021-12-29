<?php

namespace Console\App\Service\Github;

class Filters
{
    private const KEY_DATA = 'data';
    private const KEY_IS_INCLUDED = 'isIncluded';

    public const FILTER_AUTHOR = 'author';
    public const FILTER_FILE_EXTENSION = 'fileExtension';
    public const FILTER_NUM_APPROVED = 'numApproved';
    public const FILTER_REPOSITORY_NAME = 'repositoryName';
    public const FILTER_REPOSITORY_PRIVATE = 'repositoryPrivate';
    public const FILTER_REVIEWER = 'reviewer';

    /**
     * @var array
     */
    protected $filters = [];

    public function addFilter(string $filter, array $data, bool $isIncluded)
    {
        if (!isset($this->filters[$filter])) {
            $this->filters[$filter] = [
                self::KEY_DATA => [],
                self::KEY_IS_INCLUDED => true,
            ];
        }

        $this->filters[$filter][self::KEY_DATA] = $data;
        $this->filters[$filter][self::KEY_IS_INCLUDED] = $isIncluded;

        return $this;
    }

    public function getFilterData(string $filter): array
    {
        return $this->filters[$filter][self::KEY_DATA];
    }

    public function hasFilter(string $filter): bool
    {
        return isset($this->filters[$filter]);
    }

    public function isFilterIncluded(string $filter): bool
    {
        return $this->filters[$filter][self::KEY_IS_INCLUDED];
    }
}
