<?php

namespace Console\App\Service\Github;

use DateInterval;
use DateTime;

class Query
{
    public const LABEL_ON_HOLD = 'label:\"On hold\"';
    public const LABEL_QA_OK = 'label:\"QA ✔️\"';
    public const LABEL_WAITING_FOR_AUTHOR = 'label:\"waiting for author\"';
    public const LABEL_WAITING_FOR_DEV = 'label:\"waiting for dev\"';
    public const LABEL_WAITING_FOR_PM = 'label:\"waiting for PM\"';
    public const LABEL_WAITING_FOR_QA = 'label:\"waiting for QA\"';
    public const LABEL_WAITING_FOR_REBASE = 'label:\"waiting for rebase\"';
    public const LABEL_WAITING_FOR_UX = 'label:\"waiting for UX\"';
    public const LABEL_WAITING_FOR_WORDING = 'label:\"waiting for Wording\"';
    public const LABEL_WIP = 'label:WIP';

    protected const REQUESTS = [
        // Check Merged PR (Milestone, Issue & Milestone)
        'Merged PR' => 'is:merged merged:>%dateYesterday%',
        // Check PR waiting for merge
        self::REQUEST_PR_WAITING_FOR_MERGE => 'is:open archived:false ' . self::LABEL_QA_OK
            .' -'.self::LABEL_WAITING_FOR_AUTHOR
            .' -'.self::LABEL_WAITING_FOR_PM
            .' -'.self::LABEL_WAITING_FOR_REBASE,
        // Check PR waiting for QA
        self::REQUEST_PR_WAITING_FOR_QA => 'is:open archived:false ' . self::LABEL_WAITING_FOR_QA
          .' -'.self::LABEL_WAITING_FOR_AUTHOR
          .' -'.self::LABEL_WAITING_FOR_DEV,
        // Check PR waiting for Rebase
        'PR Waiting for Rebase' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_REBASE,
        // Check PR waiting for PM
        'PR Waiting for PM' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_PM .' -'.self::LABEL_WAITING_FOR_AUTHOR,
        // Check PR waiting for UX
        'PR Waiting for UX' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_UX .' -'.self::LABEL_WAITING_FOR_AUTHOR,
        // Check PR waiting for Wording
        'PR Waiting for Wording' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_WORDING .' -'.self::LABEL_WAITING_FOR_AUTHOR,
        // Check PR waiting for dev
        self::REQUEST_PR_WAITING_FOR_DEV => 'is:open archived:false ' . self::LABEL_WAITING_FOR_DEV,
        // Check PR waiting for Author
        'PR Waiting for Author' => 'is:open archived:false ' . self::LABEL_WAITING_FOR_AUTHOR,
        // Check PR waiting for Review 
        self::REQUEST_PR_WAITING_FOR_REVIEW => 'is:open archived:false'
            .' -' . self::LABEL_ON_HOLD
            .' -' . self::LABEL_QA_OK
            .' -' . self::LABEL_WAITING_FOR_AUTHOR
            .' -' . self::LABEL_WAITING_FOR_PM
            .' -' . self::LABEL_WAITING_FOR_QA
            .' -' . self::LABEL_WAITING_FOR_REBASE
            .' -' . self::LABEL_WAITING_FOR_UX
            .' -' . self::LABEL_WAITING_FOR_WORDING
            .' -' . self::LABEL_WAITING_FOR_QA
            .' -' . self::LABEL_WIP,
    ]; 
    public const REQUEST_PR_WAITING_FOR_DEV = 'PR Waiting for Dev';
    public const REQUEST_PR_WAITING_FOR_MERGE = 'PR Waiting for Merge';
    public const REQUEST_PR_WAITING_FOR_QA = 'PR Waiting for QA';
    public const REQUEST_PR_WAITING_FOR_REVIEW = 'PR Waiting for Review';

    /**
     * @var string
     */
    protected $pageAfter = '';

    /**
     * @var string
     */
    protected $query = '';

    /**
     * @return  string
     */ 
    public function getPageAfter(): string
    {
        return $this->pageAfter;
    }

    /**
     * @return  string
     */ 
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return  array
     */ 
    static function getRequests(): array
    {
        $requests = self::REQUESTS;

        $dateYesterDay = new DateTime();
        $dateYesterDay->sub(new DateInterval('P1D'));

        foreach ($requests as &$value) {
          $value = str_replace([
            '%dateYesterDay%'
          ], [
            $dateYesterDay->format('Y-m-d')
          ], $value);
        }

        return $requests;
    }

    /**
     * @param  string  $pageAfter
     * @return  self
     */ 
    public function setPageAfter(string $pageAfter): self
    {
        $this->pageAfter = $pageAfter;
        return $this;
    }

    /**
     * @param  string  $query
     * @return  self
     */ 
    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }    

    public function __toString(): string
    {
        return '{
            search(query: "' . $this->getQuery() . '", type: ISSUE, last: 100' . ($this->getPageAfter() == '' ? '' : ', after: "' . $this->getPageAfter() . '"'). ') {
              issueCount
              pageInfo {
                endCursor
                hasNextPage
              }
              edges {
                node {
                  ... on PullRequest {
                    number
                    author {
                      login
                      url
                    }
                    url
                    title
                    body
                    createdAt
                    merged
                    files(last: 100) {
                      nodes {
                        path
                      }
                    }
                    milestone {
                      title
                    }
                    repository {
                      name
                      url
                      isPrivate
                    }
                    reviews(last: 100) {
                      totalCount
                      nodes {
                        author {
                          login
                        }
                        state
                        createdAt
                      }              
                    }
                  }
                }
              }
            }
          }';
    }
}