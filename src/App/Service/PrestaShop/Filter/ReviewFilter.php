<?php

namespace Console\App\Service\PrestaShop\Filter;

use DateTime;

/**
 * This class is the holder of filter parameters
 */
class ReviewFilter
{
    /**
     * @var DateTime
     */
    private $startDate;

    /**
     * @var DateTime
     */
    private $endDate;

    /**
     * @var string
     */
    private $reviewer;

    public function __construct(string $reviewer, DateTime $startDate, DateTime $endDate)
    {
        $this->reviewer = $reviewer;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * @return DateTime
     */
    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    /**
     * @return DateTime
     */
    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    /**
     * @return string
     */
    public function getReviewer(): string
    {
        return $this->reviewer;
    }
}
