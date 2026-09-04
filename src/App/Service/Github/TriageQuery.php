<?php

namespace Console\App\Service\Github;

/**
 * Search query for the weekly triage agent.
 *
 * Subclasses Query rather than extending it in place: the base node selection is
 * shared by every other command and each field added there is paid for on every
 * one of their paginated calls. Triage needs a wider set - update time, author
 * association, recent comments, review decision, base branch - so it brings its
 * own selection and reuses Github::search() for the pagination.
 */
class TriageQuery extends Query
{
    /**
     * How many trailing comments to fetch. The last few carry the maintainer
     * discussion that most often settles a severity; the whole thread would be
     * mostly noise and tokens.
     */
    private const COMMENTS = 3;

    public function __toString(): string
    {
        $after = $this->getPageAfter() === ''
            ? ''
            : ', after: "' . $this->getPageAfter() . '"';

        return '{
            search(query: "' . $this->getQuery() . '", type: ISSUE, first: 50' . $after . ') {
              issueCount
              pageInfo {
                endCursor
                hasNextPage
              }
              edges {
                node {
                  __typename
                  ... on Issue {
                    number
                    state
                    title
                    body
                    url
                    createdAt
                    updatedAt
                    authorAssociation
                    author { login }
                    milestone { title }
                    reactions { totalCount }
                    labels(last: 100) { nodes { name } }
                    comments(last: ' . self::COMMENTS . ') {
                      totalCount
                      nodes {
                        body
                        createdAt
                        authorAssociation
                        author { login }
                      }
                    }
                  }
                  ... on PullRequest {
                    number
                    state
                    title
                    body
                    url
                    createdAt
                    updatedAt
                    authorAssociation
                    isDraft
                    baseRefName
                    additions
                    deletions
                    changedFiles
                    reviewDecision
                    author { login }
                    milestone { title }
                    labels(last: 100) { nodes { name } }
                    reviews(last: 5) {
                      nodes {
                        state
                        submittedAt
                        author { login }
                      }
                    }
                    commits(last: 1) { nodes { commit { committedDate } } }
                    comments(last: ' . self::COMMENTS . ') {
                      totalCount
                      nodes {
                        body
                        createdAt
                        authorAssociation
                        author { login }
                      }
                    }
                  }
                }
              }
            }
          }';
    }
}
